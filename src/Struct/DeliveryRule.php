<?php declare(strict_types=1);

namespace DrDotHouse\MonthlyDelivery\Struct;

/**
 * Unveränderliche Berechnungsregel eines Lieferprofils (z. B. pro Lieferant).
 *
 * Standard (laut Anforderung):
 *  - Eintreffen beim Shop: letzter Mittwoch des Monats
 *  - Bestellschluss: 7 Tage vorher (= vorletzter Mittwoch) um 23:59:59 Uhr
 *  - Versand an den Kunden: der nächste Montag nach dem Eintreffen
 */
final class DeliveryRule
{
    public const WEEKDAY_MONDAY = 1;
    public const WEEKDAY_WEDNESDAY = 3;
    public const WEEKDAY_SUNDAY = 7;

    /** Vorkommen des Wochentags im Monat: -1 = letzter, -2 = vorletzter, 1 = erster … 4 = vierter */
    public const OCCURRENCE_LAST = -1;

    /**
     * @param list<int> $excludedMonths Monate (1–12), in denen der Lieferant nicht liefert
     */
    public function __construct(
        private readonly string $technicalName = 'standard',
        private readonly string $name = 'Standard',
        private readonly int $arrivalWeekday = self::WEEKDAY_WEDNESDAY,
        private readonly int $arrivalOccurrence = self::OCCURRENCE_LAST,
        private readonly int $deadlineOffsetDays = 7,
        private readonly string $deadlineTime = '23:59:59',
        private readonly int $shipWeekday = self::WEEKDAY_MONDAY,
        private readonly array $excludedMonths = [],
    ) {
        if ($arrivalWeekday < 1 || $arrivalWeekday > 7) {
            throw new \InvalidArgumentException('arrivalWeekday muss zwischen 1 (Montag) und 7 (Sonntag) liegen.');
        }
        if ($shipWeekday < 1 || $shipWeekday > 7) {
            throw new \InvalidArgumentException('shipWeekday muss zwischen 1 (Montag) und 7 (Sonntag) liegen.');
        }
        if (!\in_array($arrivalOccurrence, [-2, -1, 1, 2, 3, 4], true)) {
            throw new \InvalidArgumentException('arrivalOccurrence muss -2, -1, 1, 2, 3 oder 4 sein.');
        }
        if ($deadlineOffsetDays < 0 || $deadlineOffsetDays > 27) {
            throw new \InvalidArgumentException('deadlineOffsetDays muss zwischen 0 und 27 liegen.');
        }
        if (!self::isValidDeadlineTime($deadlineTime)) {
            throw new \InvalidArgumentException('deadlineTime muss im Format HH:MM oder HH:MM:SS angegeben werden.');
        }
        if (\count(array_unique($excludedMonths)) >= 12) {
            throw new \InvalidArgumentException('Es dürfen nicht alle 12 Monate ausgeschlossen werden.');
        }
        foreach ($excludedMonths as $month) {
            if ($month < 1 || $month > 12) {
                throw new \InvalidArgumentException('excludedMonths darf nur Werte von 1 bis 12 enthalten.');
            }
        }
    }

    public static function isValidDeadlineTime(string $value): bool
    {
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $value) === 1;
    }

    /**
     * Strenge Prüfung beim Speichern (wie in der Admin-Maske): nur Zahlen 1–12, getrennt durch Komma,
     * Semikolon oder Leerzeichen, nicht alle 12 Monate. Gibt die Fehlermeldung zurück oder null.
     */
    public static function excludedMonthsError(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $months = [];
        foreach (preg_split('/[\s,;]+/', trim($value)) ?: [] as $part) {
            if ($part === '') {
                continue;
            }
            if (!ctype_digit($part) || (int) $part < 1 || (int) $part > 12) {
                return 'excludedMonths darf nur Werte von 1 bis 12 enthalten.';
            }
            $months[(int) $part] = true;
        }

        return \count($months) >= 12 ? 'Es dürfen nicht alle 12 Monate ausgeschlossen werden.' : null;
    }

    /**
     * Wandelt eine kommagetrennte Liste wie "8, 12" in Monatszahlen um. Ungültige Werte werden ignoriert.
     *
     * @return list<int>
     */
    public static function parseExcludedMonths(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        $months = [];
        foreach (preg_split('/[\s,;]+/', $value) ?: [] as $part) {
            if ($part === '' || !ctype_digit($part)) {
                continue;
            }
            $month = (int) $part;
            if ($month >= 1 && $month <= 12) {
                $months[] = $month;
            }
        }

        return array_values(array_unique($months));
    }

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getArrivalWeekday(): int
    {
        return $this->arrivalWeekday;
    }

    public function getArrivalOccurrence(): int
    {
        return $this->arrivalOccurrence;
    }

    public function getDeadlineOffsetDays(): int
    {
        return $this->deadlineOffsetDays;
    }

    public function getDeadlineTime(): string
    {
        return \strlen($this->deadlineTime) === 5 ? $this->deadlineTime . ':00' : $this->deadlineTime;
    }

    public function getShipWeekday(): int
    {
        return $this->shipWeekday;
    }

    /**
     * @return list<int>
     */
    public function getExcludedMonths(): array
    {
        return $this->excludedMonths;
    }
}
