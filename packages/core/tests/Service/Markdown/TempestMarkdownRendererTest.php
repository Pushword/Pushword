<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Service\Markdown;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Service\Markdown\TempestMarkdownRenderer;
use Pushword\Core\Tests\Support\HtmlEquivalence;

final class TempestMarkdownRendererTest extends TestCase
{
    /** @return iterable<string, array{string, ?string}> */
    public static function cases(): iterable
    {
        yield 'plain text' => ['Une marche en montagne.', "<p>Une marche en montagne.</p>\n"];
        yield 'escaped quote' => ['Une « marche » dite "facile".', "<p>Une « marche » dite &quot;facile&quot;.</p>\n"];
        yield 'heading without Tempest id' => ['## Une marche facile', "<h2>Une marche facile</h2>\n"];
        yield 'heading with a Tempest trailing id' => ['## Une marche ## facile', "<h2>Une marche</h2>\n"];
        yield 'heading with a bare trailing marker' => ['## Une marche ##', "<h2>Une marche</h2>\n"];
        yield 'heading with an accent keeps no id' => ['## Étape à Prébois', "<h2>Étape à Prébois</h2>\n"];
        yield 'heading quote' => ['### La "montagne"', "<h3>La &quot;montagne&quot;</h3>\n"];
        yield 'numbered heading with emphasis' => ['### 1. Etape **facile**', "<h3>1. Etape <strong>facile</strong></h3>\n"];
        yield 'emphasis' => ['Une **marche** et _un voyage_.', "<p>Une <strong>marche</strong> et <em>un voyage</em>.</p>\n"];
        yield 'ordinary link' => ['Voir [la marche](/marche).', "<p>Voir <a href=\"/marche\">la marche</a>.</p>\n"];
        yield 'quoted link label' => ['Voir ["la marche"](/marche).', "<p>Voir <a href=\"/marche\">&quot;la marche&quot;</a>.</p>\n"];
        yield 'fragment link and text quote' => ['Voir [les dates](#dates) en "2026".', "<p>Voir <a href=\"#dates\">les dates</a> en &quot;2026&quot;.</p>\n"];
        yield 'inline code' => ['Un `code` simple.', "<p>Un <code>code</code> simple.</p>\n"];
        yield 'inline code inside bold preserves code text' => ['**`a < b &lt;`**', "<p><strong><code>a &lt; b &amp;lt;</code></strong></p>\n"];
        yield 'leading heading id' => ["{id=programme}\n## Une marche facile", "<h2 id=\"programme\">Une marche facile</h2>\n"];
        yield 'leading paragraph id' => ["{id=programme}\nUne marche facile", "<p id=\"programme\">Une marche facile</p>\n"];
        yield 'paragraph class' => ['Ce que ça demande {.pp-kicker}', "<p class=\"pp-kicker\">Ce que ça demande</p>\n"];
        yield 'heading class' => ['## Une marche {.pp-kicker}', "<h2 class=\"pp-kicker\">Une marche</h2>\n"];
        yield 'heading class and id' => ["{id=rdv .ico-location}\n## Rendez-vous", "<h2 class=\"ico-location\" id=\"rdv\">Rendez-vous</h2>\n"];
        yield 'inline heading class and id' => ['## Hi {.a #b}', "<h2 class=\"a\" id=\"b\">Hi</h2>\n"];
        yield 'link class' => ['Voir [la marche](/marche){.ninja}.', "<p>Voir <a class=\"ninja\" href=\"/marche\">la marche</a>.</p>\n"];
        yield 'mixed link classes' => ['[A](/a){.ninja} et [B](/b).', "<p><a class=\"ninja\" href=\"/a\">A</a> et <a href=\"/b\">B</a>.</p>\n"];
        yield 'unordered list' => ["- Une marche\n- Un voyage", "<ul>\n<li>Une marche</li>\n<li>Un voyage</li>\n</ul>\n"];
        yield 'task list checkboxes' => ["- [x] Done\n- [ ] Pending", "<ul>\n<li><input checked=\"\" disabled=\"\" type=\"checkbox\"> Done</li>\n<li><input disabled=\"\" type=\"checkbox\"> Pending</li>\n</ul>\n"];
        yield 'list with link class' => ["- [Une marche](/marche){.ninja}\n- Un voyage", "<ul>\n<li><a class=\"ninja\" href=\"/marche\">Une marche</a></li>\n<li>Un voyage</li>\n</ul>\n"];
        yield 'namespaced PHP class in list code' => ['- **Events:** See `Pushword\\Core\\Event\\PushwordEvents`.', "<ul>\n<li><strong>Events:</strong> See <code>Pushword\\Core\\Event\\PushwordEvents</code>.</li>\n</ul>\n"];
        yield 'wrapped link label in list' => ["- The [ELTS\n  price](/pricing) increased.", "<ul>\n<li>The <a href=\"/pricing\">ELTS\nprice</a> increased.</li>\n</ul>\n"];
        yield 'ordered list' => ['1. Etape', "<ol>\n<li>Etape</li>\n</ol>\n"];
        yield 'ordered list start' => ['2. Etape', "<ol start=\"2\">\n<li>Etape</li>\n</ol>\n"];
        yield 'soft break' => ["Une ligne\nDeux lignes", "<p>Une ligne\nDeux lignes</p>\n"];
        yield 'horizontal rule despite Tempest front matter' => ['---', "<hr />\n"];
        yield 'literal tilde' => ['Environ ~800 m.', "<p>Environ ~800 m.</p>\n"];
        yield 'tilde in emphasis' => ['_~11 km_', "<p><em>~11 km</em></p>\n"];
        yield 'empty input' => ['', ''];
        yield 'simple fourth-level heading' => ['#### Fin', "<h4>Fin</h4>\n"];
        yield 'spaced asterisk rule' => ['* * *', "<hr />\n"];
        yield 'triple emphasis' => ['***marche***', "<p><strong><em>marche</em></strong></p>\n"];
        yield 'ordinary double underscores' => ['Le texte __important__ reste compatible.', "<p>Le texte <strong>important</strong> reste compatible.</p>\n"];
        yield 'numbered underscore stays literal' => ['3_h de marche._', "<p>3_h de marche._</p>\n"];
        yield 'malformed marker keeps subsequent number literal' => ["1)Départ\n2) Retour", "<p>1)Départ\n2) Retour</p>\n"];
        yield 'ambiguous bold follows Tempest emphasis' => ['pain**, mais les** horaires\\*\\*', "<p>pain<strong>, mais les</strong> horaires**</p>\n"];
        yield 'ambiguous underscore across hard break follows Tempest emphasis' => ["word_.  \nNext._", "<p>word<em>.<br />\nNext.</em></p>\n"];
        yield 'intraword underscores stay literal' => ['a_b_c', "<p>a_b_c</p>\n"];
        yield 'strikethrough' => ['~~marche~~', "<p><del>marche</del></p>\n"];
        yield 'trailing space in emphasis stays literal' => ['_Une marche _', "<p>_Une marche _</p>\n"];
        yield 'trailing space in bold stays literal' => ['**Une marche **', "<p>**Une marche **</p>\n"];
        yield 'literal brackets stay literal' => ['Voir [LIEN_AFFILIATION] ici.', "<p>Voir [LIEN_AFFILIATION] ici.</p>\n"];
        yield 'social handles stay literal' => ['Suivez {x:example}, {gh:example} et {bsky:example}.', "<p>Suivez {x:example}, {gh:example} et {bsky:example}.</p>\n"];
        yield 'social handle in a list stays literal' => ['- {gh:example}', "<ul>\n<li>{gh:example}</li>\n</ul>\n"];
        yield 'quoted link destination' => ['[marche](a"b)', "<p><a href=\"a%22b\">marche</a></p>\n"];
        yield 'image in custom star list uses CommonMark' => ["* Départ\n* ![](carte.jpg)", null];
        yield 'unicode link destination is encoded' => ['[marche](école)', "<p><a href=\"%C3%A9cole\">marche</a></p>\n"];
        yield 'space in link destination stays literal' => ['[marche](a b)', "<p>[marche](a b)</p>\n"];
        yield 'apostrophe in inline code keeps the same code text' => ["Un `x'y` code.", "<p>Un <code>x'y</code> code.</p>\n"];
        yield 'named link class' => ['[la marche](/marche){class="ninja"}', "<p><a class=\"ninja\" href=\"/marche\">la marche</a></p>\n"];
        yield 'link with class and id' => ['[link](/docs){.button #docs}', "<p><a class=\"button\" id=\"docs\" href=\"/docs\">link</a></p>\n"];
        yield 'obfuscated link uses CommonMark' => ['#[la marche](/marche)', null];
        yield 'obfuscated link with two attributes uses CommonMark' => ['#[*Café*](https://example.com/café){.button target="_blank"}', null];
        yield 'obfuscated link with id uses CommonMark' => ['#[docs](/docs){#main-link}', null];
        yield 'email without extension stays literal' => ['contact@example.com', "<p>contact@example.com</p>\n"];
        yield 'phone uses Pushword' => ['01 23 45 67 89', null];
        yield 'international phone uses Pushword' => ['+33 7 81 32 36 55', null];
        yield 'date uses Pushword' => ['date(Y)', null];
        yield 'attributed list' => ["{id=programme}\n- Etape", "<ul id=\"programme\">\n<li>Etape</li>\n</ul>\n"];
        yield 'leading hash heading id' => ["{#programme}\n## Une marche facile", "<h2 id=\"programme\">Une marche facile</h2>\n"];
        yield 'leading hash paragraph id' => ["{#programme}\nUne marche facile", "<p id=\"programme\">Une marche facile</p>\n"];
        yield 'leading hash heading class and id' => ["{#rdv .ico-location}\n## Rendez-vous", "<h2 class=\"ico-location\" id=\"rdv\">Rendez-vous</h2>\n"];
        yield 'leading hash attributed list' => ["{#programme}\n- Etape", "<ul id=\"programme\">\n<li>Etape</li>\n</ul>\n"];
        yield 'leading hash attributed blockquote' => ["{#citation}\n> Une longue citation.", "<blockquote id=\"citation\">\n<p>Une longue citation.</p>\n</blockquote>\n"];
        yield 'nested list' => ["- Une marche\n  - Un voyage", "<ul>\n<li>Une marche\n<ul>\n<li>Un voyage</li>\n</ul>\n</li>\n</ul>\n"];
        yield 'simple table' => ["| A | B |\n|---|---|\n| x | y |", "<table>\n<thead>\n<tr>\n<th>A</th>\n<th>B</th>\n</tr>\n</thead>\n<tbody>\n<tr>\n<td>x</td>\n<td>y</td>\n</tr>\n</tbody>\n</table>\n"];
        yield 'aligned table' => ["| A | B | C |\n| :--- | :--: | ---: |\n| 1 | 2 | 3 |", "<table>\n<thead>\n<tr>\n<th align=\"left\">A</th>\n<th align=\"center\">B</th>\n<th align=\"right\">C</th>\n</tr>\n</thead>\n<tbody>\n<tr>\n<td align=\"left\">1</td>\n<td align=\"center\">2</td>\n<td align=\"right\">3</td>\n</tr>\n</tbody>\n</table>\n"];
        yield 'empty table heading' => ["| | B |\n|---|---|\n| x | y |", "<table>\n<thead>\n<tr>\n<th></th>\n<th>B</th>\n</tr>\n</thead>\n<tbody>\n<tr>\n<td>x</td>\n<td>y</td>\n</tr>\n</tbody>\n</table>\n"];
        yield 'indented code' => ['    code', "<pre><code>code\n</code></pre>\n"];
        yield 'html' => ['<span>texte</span>', "<p><span>texte</span></p>\n"];
        yield 'raw input' => ['<input type="checkbox" disabled="" />', "<input type=\"checkbox\" disabled=\"\" />\n"];
        yield 'entity' => ['A & B', "<p>A &amp; B</p>\n"];
    }

    #[DataProvider('cases')]
    public function testOnlyMatchingSubsetUsesTempest(string $source, ?string $expected): void
    {
        $actual = new TempestMarkdownRenderer()->render($source);
        if (null === $expected) {
            self::assertNull($actual);

            return;
        }

        self::assertNotNull($actual);
        self::assertSame(HtmlEquivalence::structure($expected), HtmlEquivalence::structure($actual));
    }

    public function testLinkInsideAmbiguousEmphasisKeepsItsHref(): void
    {
        $html = new TempestMarkdownRenderer()->render('_Laura__, la_ [_Colombie_](/blog/colombie)');

        self::assertNotNull($html);
        self::assertStringContainsString('<a href="/blog/colombie"><em>Colombie</em></a>', $html);
    }
}
