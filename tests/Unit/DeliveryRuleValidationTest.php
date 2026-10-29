<?php declare(strict_types=1);

namespace DrDotHouse\MonthlyDelivery\Tests\Unit;

use DrDotHouse\MonthlyDelivery\Struct\DeliveryRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DeliveryRuleValidationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function deadlineTimes(): iterable
    {
        yield 'HH:MM:SS' => ['23:59:59', true];
        yield 'HH:MM' => ['08:30', true];
        yield 'Stunde 24' => ['24:00', false];
        yield 'Minute 99' => ['25:99:00', false];
        yield 'leer' => ['', false];
        yield 'Text' => ['abends', false];
    }

    #[DataProvider('deadlineTimes')]
    public function testDeadlineTime(string $value, bool $valid): void
    {
        static::assertSame($valid, DeliveryRule::isValidDeadlineTime($value));
    }

    /**
     * @return iterable<string, array{?string, bool}>
     */
    public static function excludedMonths(): iterable
    {
        yield 'leer' => [null, true];
        yield 'nur Leerzeichen' => ['  ', true];
        yield 'Komma' => ['8, 12', true];
        yield 'Semikolon und Leerzeichen' => ['1;2 3', true];
        yield 'elf Monate' => ['1,2,3,4,5,6,7,8,9,10,11', true];
        yield 'alle zwölf' => ['1,2,3,4,5,6,7,8,9,10,11,12', false];
        yield 'Monat 13' => ['13', false];
        yield 'Monat 0' => ['0', false];
        yield 'Text' => ['August', false];
        yield 'negativ' => ['-1', false];
    }

    #[DataProvider('excludedMonths')]
    public function testExcludedMonths(?string $value, bool $valid): void
    {
        static::assertSame($valid, DeliveryRule::excludedMonthsError($value) === null);
    }
}
