<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Component\EntityFilter\Filter;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Component\EntityFilter\Filter\Date;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class DurationSinceShortcodeTest extends KernelTestCase
{
    /** @return iterable<string, array{string, ?string, string}> */
    public static function examples(): iterable
    {
        $today = new DateTimeImmutable('today');
        $anniversary = $today->modify('-2 years');
        $lastMonth = $today->modify('first day of last month');
        $lastMonth = $lastMonth->setDate((int) $lastMonth->format('Y'), (int) $lastMonth->format('m'), min(28, (int) $today->format('d')));

        $months = (int) $today->format('n') - 1;

        yield 'today' => [$today->format('Y-m-d'), 'en', '0 months'];
        yield 'less than one month' => [$today->modify('-1 day')->format('Y-m-d'), 'en', '0 months'];
        yield 'singular month' => [$lastMonth->format('Y-m-d'), 'en', '1 month'];
        yield 'french month' => [$lastMonth->format('Y-m-d'), 'fr', "1\u{00A0}mois"];
        yield 'default locale' => [$lastMonth->format('Y-m-d'), null, "1\u{00A0}mois"];
        yield 'french regional locale' => [$lastMonth->format('Y-m-d'), 'fr_FR', "1\u{00A0}mois"];
        yield 'eighteen months' => [$lastMonth->modify('-17 months')->format('Y-m-d'), 'en', '18 months'];
        yield 'french eighteen months' => [$lastMonth->modify('-17 months')->format('Y-m-d'), 'fr', "18\u{00A0}mois"];
        yield 'one year still uses months' => [$today->modify('-1 year')->format('Y-m-d'), 'en', '12 months'];
        yield 'before two years' => [$anniversary->modify('+1 day')->format('Y-m-d'), 'en', '23 months'];
        yield 'exactly two years' => [$anniversary->format('Y-m-d'), 'en', '2 years'];
        yield 'after two years' => [$anniversary->modify('-1 day')->format('Y-m-d'), 'en', '2 years'];
        yield 'completed years' => [$today->modify('-3 years')->format('Y-m-d'), 'fr', '3 ans'];
        yield 'german years' => [$anniversary->format('Y-m-d'), 'de', '2 Jahre'];
        yield 'spanish month' => [$lastMonth->format('Y-m-d'), 'es', '1 mes'];
        yield 'future months' => [$today->modify('+1 year')->format('Y-m-d'), 'en', '-12 months'];
        yield 'tomorrow' => [$today->modify('+1 day')->format('Y-m-d'), 'en', '0 months'];
        yield 'future years' => [$today->modify('+2 years')->format('Y-m-d'), 'en', '-2 years'];
        yield 'year only' => [(string) ((int) $today->format('Y') - 3), 'en', '3 years'];
        yield 'current year' => [$today->format('Y'), 'en', $months.' '.(1 === $months ? 'month' : 'months')];
    }

    #[DataProvider('examples')]
    public function testDurationSinceShortcode(string $date, ?string $locale, string $expected): void
    {
        self::bootKernel();
        $filter = self::getContainer()->get(Date::class);

        self::assertSame($expected, $filter->convertDateShortCode('durationSince('.$date.')', $locale));
    }

    /** @return iterable<string, array{string}> */
    public static function literals(): iterable
    {
        foreach (['durationSince()', 'durationSince(abc)', 'durationSince(14)', 'durationSince(2014-6-15)', 'durationSince(2014-02-29)', 'durationSince(2014-13-01)', 'durationSince(2014-01-32)', 'durationSince(2014', 'notdurationSince(2014)'] as $literal) {
            yield $literal => [$literal];
        }
    }

    #[DataProvider('literals')]
    public function testInvalidShortcodeStaysLiteral(string $source): void
    {
        self::bootKernel();

        self::assertSame($source, self::getContainer()->get(Date::class)->convertDateShortCode($source, 'en'));
    }
}
