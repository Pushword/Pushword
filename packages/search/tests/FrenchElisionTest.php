<?php

declare(strict_types=1);

namespace Pushword\Search\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pushword\Search\Service\FrenchElision;

final class FrenchElisionTest extends TestCase
{
    private const string B = FrenchElision::BOUNDARY;

    /** @return iterable<string, array{string, string}> */
    public static function provideMarkedText(): iterable
    {
        yield 'typographic apostrophe' => ['Comment fonctionnent l’acompte ?', 'Comment fonctionnent l’'.self::B.'acompte ?'];
        yield 'straight apostrophe' => ["Versez l'acompte", "Versez l'".self::B.'acompte'];
        yield 'upper case' => ['L’ACOMPTE', 'L’'.self::B.'ACOMPTE'];
        yield 'accented word' => ['partir d’été', 'partir d’'.self::B.'été'];
        yield 'accented capital' => ['D’Été', 'D’'.self::B.'Été'];
        yield 'longer particles' => ["jusqu'à lorsqu’on qu’il", "jusqu'".self::B.'à lorsqu’'.self::B.'on qu’'.self::B.'il'];
        yield 'particle ending a longer word' => ['aujourd’hui', 'aujourd’hui'];
        yield 'word that is not a particle' => ["presqu'île rock'n", "presqu'île rock'n"];
        yield 'particle without a word after it' => ["l' 2", "l' 2"];
        yield 'empty text' => ['', ''];
        yield 'particle glued to a digit' => ["2l'a", "2l'a"];
        yield 'common contractions' => ["c'est n'est s'il t'a m'a j'ai", "c'".self::B."est n'".self::B."est s'".self::B."il t'".self::B."a m'".self::B."a j'".self::B.'ai'];
    }

    #[DataProvider('provideMarkedText')]
    public function testMarkForIndexSplitsOnlyElidedParticles(string $text, string $expected): void
    {
        self::assertSame($expected, FrenchElision::markForIndex($text));
    }

    public function testMarkingTwiceAddsNoSecondBoundary(): void
    {
        $marked = FrenchElision::markForIndex('l’acompte');

        self::assertSame($marked, FrenchElision::markForIndex($marked));
    }

    public function testUnmarkRestoresTheOriginalText(): void
    {
        $text = "Jusqu’à l'Été, d’ailleurs";

        self::assertSame($text, FrenchElision::unmark(FrenchElision::markForIndex($text)));
    }

    /** @return iterable<string, array{string, string}> */
    public static function provideQueries(): iterable
    {
        yield 'typographic apostrophe' => ['l’acompte', 'acompte'];
        yield 'straight apostrophe' => ["L'Acompte du solde", 'Acompte du solde'];
        yield 'quoted phrase' => ['"l’acompte et le solde"', '"acompte et le solde"'];
        yield 'bare word' => ['acompte', 'acompte'];
        yield 'particle alone' => ["l'", "l'"];
        yield 'empty query' => ['', ''];
        yield 'several elisions' => ["l’acompte d'été", 'acompte été'];
        yield 'particle ending a longer word' => ['aujourd’hui', 'aujourd’hui'];
    }

    #[DataProvider('provideQueries')]
    public function testStripFromQueryDropsTheParticle(string $query, string $expected): void
    {
        self::assertSame($expected, FrenchElision::stripFromQuery($query));
    }
}
