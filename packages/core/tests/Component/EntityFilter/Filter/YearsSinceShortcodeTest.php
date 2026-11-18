<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Component\EntityFilter\Filter;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Component\EntityFilter\Filter\Date;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class YearsSinceShortcodeTest extends KernelTestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function examples(): iterable
    {
        $today = new DateTimeImmutable('today');
        $year = (int) $today->format('Y');
        $anniversary = $today->modify('-12 years');

        yield 'year' => ['yearsSince('.($year - 12).')', '12'];
        yield 'current year' => ['yearsSince('.$year.')', '0'];
        yield 'future year' => ['yearsSince('.($year + 1).')', '-1'];
        yield 'anniversary today' => ['yearsSince('.$anniversary->format('Y-m-d').')', '12'];
        yield 'anniversary passed' => ['yearsSince('.$anniversary->modify('-1 day')->format('Y-m-d').')', '12'];
        yield 'anniversary tomorrow' => ['yearsSince('.$anniversary->modify('+1 day')->format('Y-m-d').')', '11'];
        yield 'today' => ['yearsSince('.$today->format('Y-m-d').')', '0'];
        yield 'tomorrow' => ['yearsSince('.$today->modify('+1 day')->format('Y-m-d').')', '0'];
        yield 'future date' => ['yearsSince('.$today->modify('+12 years')->format('Y-m-d').')', '-12'];
        yield 'multiple occurrences' => ['yearsSince('.($year - 12).') / yearsSince('.$year.')', '12 / 0'];

        foreach (['', 'yearsSince()', 'yearsSince(abc)', 'yearsSince(14)', 'yearsSince(2014-6-15)', 'yearsSince(2014-02-29)', 'yearsSince(2014-13-01)', 'yearsSince(2014-01-32)', 'yearsSince(2014', 'notyearsSince(2014)'] as $literal) {
            yield 'literal '.$literal => [$literal, $literal];
        }
    }

    #[DataProvider('examples')]
    public function testYearsSinceShortcode(string $source, string $expected): void
    {
        self::bootKernel();
        $filter = self::getContainer()->get(Date::class);

        self::assertSame($expected, $filter->convertDateShortCode($source, 'fr'));
    }
}
