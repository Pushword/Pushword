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
        yield 'numbered heading with emphasis' => ['### 1. Etape **facile**', "<h3>1. Etape <strong>facile</strong></h3>\n"];
        yield 'emphasis' => ['Une **marche** et _un voyage_.', "<p>Une <strong>marche</strong> et <em>un voyage</em>.</p>\n"];
        yield 'ordinary link' => ['Voir [la marche](/marche).', "<p>Voir <a href=\"/marche\">la marche</a>.</p>\n"];
        yield 'quoted link label' => ['Voir ["la marche"](/marche).', "<p>Voir <a href=\"/marche\">&quot;la marche&quot;</a>.</p>\n"];
        yield 'fragment link and text quote' => ['Voir [les dates](#dates) en "2026".', "<p>Voir <a href=\"#dates\">les dates</a> en &quot;2026&quot;.</p>\n"];
        yield 'inline code' => ['Un `code` simple.', "<p>Un <code>code</code> simple.</p>\n"];
        yield 'leading heading id' => ["{id=programme}\n## Une marche facile", "<h2 id=\"programme\">Une marche facile</h2>\n"];
        yield 'leading paragraph id' => ["{id=programme}\nUne marche facile", "<p id=\"programme\">Une marche facile</p>\n"];
        yield 'paragraph class' => ['Ce que ça demande {.pp-kicker}', "<p class=\"pp-kicker\">Ce que ça demande</p>\n"];
        yield 'heading class' => ['## Une marche {.pp-kicker}', "<h2 class=\"pp-kicker\">Une marche</h2>\n"];
        yield 'heading class and id' => ["{id=rdv .ico-location}\n## Rendez-vous", "<h2 class=\"ico-location\" id=\"rdv\">Rendez-vous</h2>\n"];
        yield 'link class' => ['Voir [la marche](/marche){.ninja}.', "<p>Voir <a class=\"ninja\" href=\"/marche\">la marche</a>.</p>\n"];
        yield 'mixed link classes' => ['[A](/a){.ninja} et [B](/b).', "<p><a class=\"ninja\" href=\"/a\">A</a> et <a href=\"/b\">B</a>.</p>\n"];
        yield 'unordered list' => ["- Une marche\n- Un voyage", "<ul>\n<li>Une marche</li>\n<li>Un voyage</li>\n</ul>\n"];
        yield 'list with link class' => ["- [Une marche](/marche){.ninja}\n- Un voyage", "<ul>\n<li><a class=\"ninja\" href=\"/marche\">Une marche</a></li>\n<li>Un voyage</li>\n</ul>\n"];
        yield 'ordered list' => ['1. Etape', "<ol>\n<li>Etape</li>\n</ol>\n"];
        yield 'ordered list start' => ['2. Etape', "<ol start=\"2\">\n<li>Etape</li>\n</ol>\n"];
        yield 'soft break' => ["Une ligne\nDeux lignes", "<p>Une ligne\nDeux lignes</p>\n"];
        yield 'horizontal rule despite Tempest front matter' => ['---', "<hr />\n"];
        yield 'literal tilde' => ['Environ ~800 m.', "<p>Environ ~800 m.</p>\n"];
        yield 'tilde in emphasis' => ['_~11 km_', "<p><em>~11 km</em></p>\n"];
        yield 'empty input' => ['', null];
        yield 'triple emphasis uses CommonMark' => ['***marche***', null];
        yield 'intraword underscores use CommonMark' => ['a_b_c', null];
        yield 'strikethrough uses CommonMark' => ['~~marche~~', null];
        yield 'trailing space in emphasis uses CommonMark' => ['_Une marche _', null];
        yield 'trailing space in bold uses CommonMark' => ['**Une marche **', null];
        yield 'literal brackets use CommonMark' => ['Voir [LIEN_AFFILIATION] ici.', null];
        yield 'quoted link destination uses CommonMark' => ['[marche](a"b)', null];
        yield 'unicode link destination uses CommonMark' => ['[marche](école)', null];
        yield 'space in link destination uses CommonMark' => ['[marche](a b)', null];
        yield 'apostrophe in inline code uses CommonMark' => ["Un `x'y` code.", null];
        yield 'named link class' => ['[la marche](/marche){class="ninja"}', "<p><a class=\"ninja\" href=\"/marche\">la marche</a></p>\n"];
        yield 'obfuscated link uses CommonMark' => ['#[la marche](/marche)', null];
        yield 'email uses Pushword' => ['contact@example.com', null];
        yield 'phone uses Pushword' => ['01 23 45 67 89', null];
        yield 'international phone uses Pushword' => ['+33 7 81 32 36 55', null];
        yield 'date uses Pushword' => ['date(Y)', null];
        yield 'attributed list' => ["{id=programme}\n- Etape", null];
        yield 'nested list uses CommonMark' => ["- Une marche\n  - Un voyage", null];
        yield 'simple table' => ["| A | B |\n|---|---|\n| x | y |", "<table>\n<thead>\n<tr>\n<th>A</th>\n<th>B</th>\n</tr>\n</thead>\n<tbody>\n<tr>\n<td>x</td>\n<td>y</td>\n</tr>\n</tbody>\n</table>\n"];
        yield 'aligned table uses CommonMark' => ["| A | B | C |\n| :--- | :--: | ---: |\n| 1 | 2 | 3 |", null];
        yield 'empty table heading uses CommonMark' => ["| | B |\n|---|---|\n| x | y |", null];
        yield 'indented code' => ['    code', null];
        yield 'html' => ['<span>texte</span>', null];
        yield 'entity' => ['A & B', null];
    }

    #[DataProvider('cases')]
    public function testOnlyMatchingSubsetUsesTempest(string $source, ?string $expected): void
    {
        self::assertSame($expected, new TempestMarkdownRenderer()->render($source));
    }
}
