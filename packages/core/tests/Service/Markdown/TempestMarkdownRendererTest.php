<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Service\Markdown;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Service\Markdown\TempestMarkdownRenderer;

final class TempestMarkdownRendererTest extends TestCase
{
    /** @return iterable<string, array{string, ?string}> */
    public static function cases(): iterable
    {
        yield 'plain text' => ['Une marche en montagne.', "<p>Une marche en montagne.</p>\n"];
        yield 'escaped quote' => ['Une « marche » dite "facile".', "<p>Une « marche » dite &quot;facile&quot;.</p>\n"];
        yield 'heading without Tempest id' => ['## Une marche facile', "<h2>Une marche facile</h2>\n"];
        yield 'heading quote' => ['### La "montagne"', "<h3>La &quot;montagne&quot;</h3>\n"];
        yield 'empty input' => ['', null];
        yield 'emphasis uses CommonMark' => ['Une *marche*.', null];
        yield 'link uses CommonMark' => ['Voir [la marche](/marche).', null];
        yield 'email uses Pushword' => ['contact@example.com', null];
        yield 'phone uses Pushword' => ['01 23 45 67 89', null];
        yield 'date uses Pushword' => ['date(Y)', null];
        yield 'front matter marker' => ['---', null];
        yield 'ordered list' => ['1. Etape', null];
        yield 'indented code' => ['    code', null];
        yield 'multiline' => ["Une ligne\nDeux lignes", null];
        yield 'html' => ['<span>texte</span>', null];
        yield 'entity' => ['A & B', null];
    }

    #[DataProvider('cases')]
    public function testOnlyMatchingSubsetUsesTempest(string $source, ?string $expected): void
    {
        self::assertSame($expected, new TempestMarkdownRenderer()->render($source));
    }
}
