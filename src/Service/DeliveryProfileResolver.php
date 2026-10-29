<?php declare(strict_types=1);

namespace DrDotHouse\MonthlyDelivery\Service;

use DrDotHouse\MonthlyDelivery\Core\Content\DeliveryProfile\DeliveryProfileCollection;
use DrDotHouse\MonthlyDelivery\Core\Content\DeliveryProfile\DeliveryProfileEntity;
use DrDotHouse\MonthlyDelivery\Struct\DeliveryRule;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Ermittelt, ob für ein Produkt ein monatlicher Liefertermin gilt und nach welcher Regel.
 *
 * Aktivierung: ausschließlich über das Produkt-Zusatzfeld custom_supplier_monthly_delivery == true
 * (bei Varianten wird das Zusatzfeld vom Hauptprodukt geerbt).
 *
 * Reihenfolge der Profil-Ermittlung:
 *  1. Zusatzfeld am Produkt (custom_supplier_monthly_delivery_profile)
 *  2. Zusatzfeld am Hersteller/Lieferanten (custom_supplier_monthly_delivery_manufacturer_profile)
 *  3. Standardprofil aus der Plugin-Konfiguration
 *  4. Eingebaute Standardregel (letzter Mittwoch / vorletzter Mittwoch / Montag danach)
 *
 * Ein deaktiviertes Profil schaltet die Anzeige für die betroffenen Produkte ab
 * (z. B. wenn ein Lieferant vorübergehend nicht liefert).
 */
class DeliveryProfileResolver implements ResetInterface
{
    public const CONFIG_DOMAIN = 'MonthlyDeliveryPlugin.config.';

    /** @var array<string, DeliveryProfileEntity>|null Schlüssel: ID und technischer Name */
    private ?array $profiles = null;

    /** @var array<string, array<string, mixed>> */
    private array $manufacturerCustomFields = [];

    /** @var array<string, true> bereits geloggte ungültige Profile – je Prozess nur einmal, sonst Log-Flut */
    private array $loggedInvalid = [];

    /**
     * @param EntityRepository<DeliveryProfileCollection> $profileRepository
     * @param EntityRepository<ProductManufacturerCollection> $manufacturerRepository
     */
    public function __construct(
        private readonly EntityRepository $profileRepository,
        private readonly EntityRepository $manufacturerRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function isActivated(ProductEntity $product): bool
    {
        $value = self::getCustomFields($product)[CustomFieldInstaller::FIELD_ACTIVE] ?? null;

        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }

    /**
     * @param iterable<ProductEntity> $products
     *
     * @return array<string, DeliveryRule> Regeln je Produkt-ID, nur für aktivierte Produkte
     */
    public function resolveRules(iterable $products, Context $context, ?string $salesChannelId = null): array
    {
        $activated = [];
        foreach ($products as $product) {
            if (self::isActivated($product)) {
                $activated[] = $product;
            }
        }

        if ($activated === []) {
            return [];
        }

        $this->loadManufacturers($activated, $context);

        $rules = [];
        foreach ($activated as $product) {
            $rule = $this->resolveRule($product, $context, $salesChannelId);
            if ($rule !== null) {
                $rules[$product->getId()] = $rule;
            }
        }

        return $rules;
    }

    public function reset(): void
    {
        $this->profiles = null;
        $this->manufacturerCustomFields = [];
    }

    private function resolveRule(ProductEntity $product, Context $context, ?string $salesChannelId): ?DeliveryRule
    {
        $reference = self::stringValue(self::getCustomFields($product)[CustomFieldInstaller::FIELD_PRODUCT_PROFILE] ?? null);

        if ($reference === null && $product->getManufacturerId() !== null) {
            $reference = self::stringValue(
                $this->manufacturerCustomFields[$product->getManufacturerId()][CustomFieldInstaller::FIELD_MANUFACTURER_PROFILE] ?? null
            );
        }

        $reference ??= self::stringValue($this->systemConfigService->get(self::CONFIG_DOMAIN . 'defaultProfile', $salesChannelId));

        if ($reference === null) {
            return new DeliveryRule();
        }

        $profile = $this->getProfiles($context)[$reference] ?? $this->getProfiles($context)[strtolower($reference)] ?? null;

        if ($profile === null) {
            $this->logger->warning('MonthlyDelivery: Lieferprofil "{profile}" für Produkt {product} nicht gefunden – Standardregel wird verwendet.', [
                'profile' => $reference,
                'product' => $product->getProductNumber(),
            ]);

            return new DeliveryRule();
        }

        if (!$profile->isActive()) {
            return null;
        }

        try {
            return $profile->toRule();
        } catch (\InvalidArgumentException $e) {
            if (isset($this->loggedInvalid[$profile->getId()])) {
                return null;
            }
            $this->loggedInvalid[$profile->getId()] = true;
            $this->logger->error('MonthlyDelivery: Lieferprofil "{profile}" ist ungültig: {message}', [
                'profile' => $profile->getTechnicalName(),
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array<string, DeliveryProfileEntity>
     */
    private function getProfiles(Context $context): array
    {
        if ($this->profiles !== null) {
            return $this->profiles;
        }

        /** @var DeliveryProfileCollection $collection */
        $collection = $this->profileRepository->search(new Criteria(), $context)->getEntities();

        $this->profiles = [];
        foreach ($collection as $profile) {
            $this->profiles[$profile->getId()] = $profile;
            $this->profiles[strtolower($profile->getTechnicalName())] = $profile;
        }

        return $this->profiles;
    }

    /**
     * @param list<ProductEntity> $products
     */
    private function loadManufacturers(array $products, Context $context): void
    {
        $missing = [];
        foreach ($products as $product) {
            $manufacturerId = $product->getManufacturerId();
            if ($manufacturerId === null || \array_key_exists($manufacturerId, $this->manufacturerCustomFields)) {
                continue;
            }

            $manufacturer = $product->getManufacturer();
            if ($manufacturer !== null) {
                $this->manufacturerCustomFields[$manufacturerId] = $manufacturer->getTranslation('customFields') ?? $manufacturer->getCustomFields() ?? [];

                continue;
            }

            $missing[$manufacturerId] = $manufacturerId;
        }

        if ($missing === []) {
            return;
        }

        /** @var ProductManufacturerCollection $manufacturers */
        $manufacturers = $this->manufacturerRepository->search(new Criteria(array_values($missing)), $context)->getEntities();

        foreach ($missing as $manufacturerId) {
            $manufacturer = $manufacturers->get($manufacturerId);
            $this->manufacturerCustomFields[$manufacturerId] = $manufacturer?->getTranslation('customFields') ?? $manufacturer?->getCustomFields() ?? [];
        }
    }

    /**
     * Zusatzfelder inkl. Vererbung vom Hauptprodukt (bei Varianten) und Übersetzungs-Fallback.
     *
     * @return array<string, mixed>
     */
    private static function getCustomFields(ProductEntity $product): array
    {
        $translated = $product->getTranslation('customFields');

        return \is_array($translated) ? $translated : ($product->getCustomFields() ?? []);
    }

    private static function stringValue(mixed $value): ?string
    {
        if (\is_array($value)) {
            $value = $value['id'] ?? null;
        }

        if (!\is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        return Uuid::isValid($value) ? strtolower($value) : $value;
    }
}
