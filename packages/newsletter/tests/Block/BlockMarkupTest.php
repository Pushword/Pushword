<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Tests\Block;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pushword\Newsletter\Block\BlockContent;
use Pushword\Newsletter\Block\BlockMarkup;

final class BlockMarkupTest extends TestCase
{
    public function testOrderReferencesParametersAndEditedTextRoundTrip(): void
    {
        $block = new BlockContent('42', 'Été', "## Manually edited\n\n[Book](https://example.test/book)\n\n![Image](/media/image.jpg)", ['locale' => 'fr']);
        $markdown = "Before\n\n".BlockMarkup::encode('catalogue', $block)."\n\nAfter";
        $decoded = BlockMarkup::blocks($markdown);
        self::assertCount(1, $decoded);
        self::assertEquals($block, $decoded[0]['content']);
        self::assertSame('catalogue', $decoded[0]['provider']);
        self::assertSame("Before\n\n".$block->markdown."\n\nAfter", BlockMarkup::expand($markdown));
    }

    public function testNestedBlocksAreRefused(): void
    {
        $nested = new BlockContent('1', 'Nested', BlockMarkup::encode('library', new BlockContent('2', 'Inner', 'Text')));
        $this->expectException(InvalidArgumentException::class);
        BlockMarkup::expand(BlockMarkup::encode('library', $nested));
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidPayloads(): iterable
    {
        yield 'invalid JSON' => ['invalid', 'Invalid newsletter block JSON.'];
        yield 'scalar payload' => ['null', 'Invalid newsletter block.'];
        yield 'missing provider' => ['{}', 'Missing newsletter block provider.'];
        yield 'missing reference' => ['{"provider":"library"}', 'Missing newsletter block reference.'];
        yield 'missing label' => ['{"provider":"library","reference":"42"}', 'Missing newsletter block label.'];
        yield 'non-string content' => ['{"provider":"library","reference":"42","label":"Block","markdown":42}', 'Missing newsletter block markdown.'];
        yield 'non-object parameters' => ['{"provider":"library","reference":"42","label":"Block","markdown":"Text","parameters":"invalid"}', 'Invalid newsletter block parameters.'];
        yield 'indexed parameters' => ['{"provider":"library","reference":"42","label":"Block","markdown":"Text","parameters":["invalid"]}', 'Invalid newsletter block parameters.'];
    }

    #[DataProvider('invalidPayloads')]
    public function testMalformedBlockPayloadsAreRefused(string $json, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains($message);
        BlockMarkup::expand("```newsletter-block\n".$json."\n```");
    }

    public function testUnclosedBlocksAreRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Malformed or nested newsletter block.');
        BlockMarkup::expand("```newsletter-block\n{}");
    }
}
