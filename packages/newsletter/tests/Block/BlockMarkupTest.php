<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Tests\Block;

use InvalidArgumentException;
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
}
