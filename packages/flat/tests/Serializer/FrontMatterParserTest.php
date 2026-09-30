<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests\Serializer;

use PHPUnit\Framework\TestCase;
use Pushword\Flat\Serializer\FrontMatterParser;

final class FrontMatterParserTest extends TestCase
{
    public function testUnquotedDatesAreHandedOnAsWritten(): void
    {
        $document = new FrontMatterParser(<<<'MD'
            ---
            publishedAt: 2026-09-30
            holdPublicationAt: 2026-10-01 09:00:00+02:00
            created_at: 2026-01-02T03:04:05Z
            ---

            Body.
            MD)->parse();

        self::assertSame('2026-09-30 00:00:00', $document->matter('publishedAt'));
        self::assertSame('2026-10-01 09:00:00+02:00', $document->matter('holdPublicationAt'));
        self::assertSame('2026-01-02 03:04:05+00:00', $document->matter('created_at'));
        self::assertSame('Body.', $document->body());
    }

    public function testOtherValuesKeepWhatYamlGivesThem(): void
    {
        $document = new FrontMatterParser(<<<'MD'
            ---
            publishedAt: 1790726400
            eventDate: 2026-09-30
            updatedAt: '2026-09-30 14:00'
            ---
            MD)->parse();

        // A plain number is not a timestamp YAML converted: the date parser rejects it as before.
        self::assertSame(1790726400, $document->matter('publishedAt'));
        self::assertSame(1790726400, $document->matter('eventDate'));
        self::assertSame('2026-09-30 14:00', $document->matter('updatedAt'));
    }
}
