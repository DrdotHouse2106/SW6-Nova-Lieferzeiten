<?php declare(strict_types=1);

namespace DrDotHouse\MonthlyDelivery\Core\Content\DeliveryProfile\Validation;

use DrDotHouse\MonthlyDelivery\Core\Content\DeliveryProfile\DeliveryProfileDefinition;
use DrDotHouse\MonthlyDelivery\Struct\DeliveryRule;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Prüft Lieferprofile beim Speichern über die DAL (Admin-API, WaWi, Skripte) mit denselben Regeln wie
 * die Admin-Maske. Ungültige Profile würden sonst bei jedem Produktladen einen Fehler loggen und die
 * Anzeige für die betroffenen Produkte abschalten.
 */
class DeliveryProfileWriteValidator implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [PreWriteValidationEvent::class => 'preValidate'];
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        foreach ($event->getCommands() as $command) {
            if ((!$command instanceof InsertCommand && !$command instanceof UpdateCommand)
                || $command->getEntityName() !== DeliveryProfileDefinition::ENTITY_NAME
            ) {
                continue;
            }

            $violations = self::validate($command->getPayload());
            if ($violations->count() > 0) {
                $event->getExceptions()->add(new WriteConstraintViolationException($violations, $command->getPath()));
            }
        }
    }

    /**
     * @param array<string, mixed> $payload Speicher-Feldnamen (deadline_time, excluded_months …)
     */
    public static function validate(array $payload): ConstraintViolationList
    {
        $violations = new ConstraintViolationList();

        if (\array_key_exists('deadline_time', $payload)) {
            $value = $payload['deadline_time'];
            if (!\is_string($value) || !DeliveryRule::isValidDeadlineTime($value)) {
                $violations->add(self::violation('deadlineTime muss im Format HH:MM oder HH:MM:SS angegeben werden.', '/deadlineTime', $value));
            }
        }

        if (\array_key_exists('excluded_months', $payload)) {
            $value = $payload['excluded_months'];
            $error = $value === null || \is_string($value)
                ? DeliveryRule::excludedMonthsError($value)
                : 'excludedMonths muss eine Liste wie „8, 12“ sein.';
            if ($error !== null) {
                $violations->add(self::violation($error, '/excludedMonths', $value));
            }
        }

        // IntField erlaubt -2 bis 4, die Berechnung kennt aber kein Vorkommen 0
        if (\array_key_exists('arrival_occurrence', $payload) && (int) $payload['arrival_occurrence'] === 0) {
            $violations->add(self::violation('arrivalOccurrence muss -2, -1, 1, 2, 3 oder 4 sein.', '/arrivalOccurrence', $payload['arrival_occurrence']));
        }

        return $violations;
    }

    private static function violation(string $message, string $path, mixed $value): ConstraintViolation
    {
        return new ConstraintViolation($message, $message, [], null, $path, $value);
    }
}
