<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Service\Markdown;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\MarkdownConverter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Component\EntityFilter\Filter\Date;
use Pushword\Core\Component\EntityFilter\FilterRegistry;
use Pushword\Core\Content\ContentPipelineFactory;
use Pushword\Core\DependencyInjection\Configuration;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\LinkProvider;
use Pushword\Core\Service\Markdown\Extension\NoticeExtension;
use Pushword\Core\Service\Markdown\Extension\PushwordExtension;
use Pushword\Core\Service\Markdown\MarkdownParser;
use Pushword\Core\Service\Markdown\TempestMarkdownRenderer;
use Pushword\Core\Service\Typographer;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Tests\Support\HtmlEquivalence;
use Pushword\Core\Twig\MediaExtension;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment as Twig;

#[Group('integration')]
final class TempestMarkdownRendererIntegrationTest extends KernelTestCase
{
    /** @return iterable<string, array{string}> */
    public static function compatibleDynamicSources(): iterable
    {
        yield 'obfuscated link' => ['Voir #[le départ](/#depart).'];
        yield 'obfuscated link with class' => ['#[Voir le départ](/#depart){.ninja}'];
        yield 'obfuscated link with id' => ['#[docs](/docs){#main-link}'];
        yield 'obfuscated link with class and target' => ['#[*Café*](https://example.com/café){.button target="_blank"}'];
        yield 'obfuscated link with angle destination' => ['#[Voir la carte](<https://example.com/carte?c=1,2&z=3>) près du départ.'];
        yield 'obfuscated angle link after a blank line' => ["Départ.\n\n#[Voir la carte](<https://example.com/carte?c=1,2&z=3>)"];
        yield 'link title with quoted text' => ['[**Loire Valley**](https://example.com/france "Loire by Bike - \\"Loire à vélo\\"")'];
        yield 'code with an angle tag in a list' => ["- Départ avec `<ele>` dans le GPX\n  Retour le soir"];
        yield 'leading heading class and id' => ["{id=rdv .ico-location}\n## Rendez-vous"];
        yield 'inline heading class and id' => ['## Hi {.a #b}'];
        yield 'leading heading class only' => ["{.ico-star}\n## Avis"];
        yield 'leading table class' => ["{.table-sticky-header}\n| A | B |\n|---|---|\n| 1 | 2 |"];
        yield 'aligned table' => ["| A | B | C |\n| :--- | :--: | ---: |\n| 1 | 2 | 3 |"];
        yield 'task list' => ["- [x] Done\n- [ ] Pending"];
        yield 'loose task list' => ["- [x] Done\n\n- [ ] Pending"];
        yield 'nested task list' => ["- [ ] parent\n  - [X] **child**\n  - regular"];
        yield 'loose task list with continuation' => ["- [x] First\n\n  Second paragraph.\n\n- [ ] Last"];
        yield 'raw input' => ['<input type="checkbox" disabled="" />'];
        yield 'raw section' => ['<section><p>Raw <em>HTML</em>.</p></section>'];
        yield 'raw script' => ['<script>const html = "<b>é</b>";</script>'];
        yield 'email autolink' => ['<contact@example.com>'];
        yield 'telephone autolink' => ['<tel:+33123456789>'];
        yield 'code attributes' => ['`<&>`{.foo #bar}'];
        yield 'code with protected link markers' => ['`[link](/docs){.button}`'];
        yield 'angle link with invalid percent' => ['[link](</bad%zz/good%2f?q=100%>)'];
        yield 'angle link with spaces and brackets' => ['[link](</a b/[c]>)'];
        yield 'angle link with title' => ["[apostrophe](</a'b> \"A ' B\")"];
        yield 'single-quoted link attribute' => ["[link](/docs){title='a b'}"];
        yield 'unicode link id' => ['[link](/docs){#café}'];
        yield 'filtered link event attributes' => ['[link](/docs){OnClick="bad" onfocus="bad" data-safe="yes"}'];
        yield 'link destination overrides href attribute' => ['[link](/docs){href="wrong" id="ok"}'];
        yield 'three link classes' => ['[link](/docs){.first class="middle" .last}'];
        yield 'titled link with attributes' => ['[*Café*](/docs "a & b"){data-x="a&b" .button #docs}'];
        yield 'indented code block' => ['    code'];
        yield 'quoted link destination' => ['[marche](a"b)'];
        yield 'link with class and id' => ['[link](/docs){.button #docs}'];
        yield 'image in a star list' => ["* Départ\n* ![](/media/no_such_image.jpg)"];
        yield 'id before a raw comment' => ["{id=signal}\n<!-- pushword:twig-error -->"];
        yield 'id before a blockquote' => ["{id=citation}\n> A long quotation.\n> — <cite>Author</cite>"];
        yield 'blank line after block attributes' => ["{#intro .lead}\n\nA paragraph."];
        yield 'encoded email' => ['Écrivez à bonjour@example.com.'];
        yield 'phone' => ['Appelez le 07 69 44 78 66.'];
        yield 'date shortcode' => ['Rendez-vous en date(Y).'];
        yield 'short notice' => ["> [!faq] Une question ?\n>\n> Une réponse courte."];
        yield 'leading notice attributes' => ["{#disclosure .text-sm}\n> [!note] Titled\n> body"];
        yield 'notice without title' => ["> [!note]\n> body"];
        yield 'notice marker with id' => ["> [!faq] A question? {#luggage}\n> Yes."];
        yield 'notice with component attributes' => ["{#luggage .compact tag=\"h2\"}\n> [!faq] A question?\n> Yes."];
        yield 'notice with attributes in both positions' => ["{#luggage}\n> [!faq] A question? {.compact tag=\"h2\"}\n> Yes."];
        yield 'notice with anchor and paragraphs' => ["> [!infoTrip] Rendez-vous {id=rdv}\n>\n> Une première réponse.\n>\n> Une seconde réponse."];
        yield 'notice with a list' => ["> [!infoTrip] Équipement\n>\n> - Une veste\n> - Un sac"];
        yield 'notice with a loose list' => ["> [!faq] Équipement\n>\n> - Une veste\n>\n> - Un sac"];
        yield 'notice with a nested ordered list' => ["> [!faq] Étapes\n>\n> - 1. Première étape\n> - 2. Deuxième étape"];
        yield 'nested list' => ["- Une marche\n  - Un voyage\n- Un retour"];
        yield 'wrapped ordered list' => ["1. Une marche sur le sentier\n   avec une [carte](/carte)\n2. Le retour"];
        yield 'list item classes' => ["- {.ico-mountain} Une marche\n- Un voyage"];
        yield 'blockquote with lazy continuation' => ["> Un voyage ensemble\n>, Et un retour"];
        yield 'heading followed by paragraph' => ["## Une marche\nUne journée en montagne."];
        yield 'setext heading' => ["Prix 2026\n-------------"];
        yield 'setext level-one heading' => ["Prix 2026\n============="];
        yield 'setext heading attributes' => ["Hi {.a #b}\n------"];
        yield 'ordered list with parenthesis markers' => ["Étapes :\n1) Départ\n2) Retour"];
        yield 'malformed marker continues an ordered list item' => ["1) Départ\n2)(Conseillé) Retour"];
        yield 'ordered list with lazy continuation' => ["2. Départ\nSuite du parcours"];
        yield 'paragraph followed by heading' => ["Une journée en montagne.\n## Le retour"];
        yield 'empty table header' => ["| | |\n|---|---|\n| x | y |"];
        yield 'table row trailing space' => ["| A | B |\n|---|---|\n| x | y | "];
        yield 'titled link' => ['Voir [la carte](/carte "Carte du trajet").'];
        yield 'table colspan and short row' => ["| Offre | Deux | Trois |\n| --- | --- | --- |\n| Location | 25 € | -> |\n| Assurance | 10 € |"];
        yield 'empty table header with colspan' => ["| | | -> |\n| --- | --- | --- |\n| | Prix | -> |"];
        yield 'three-level list' => ["- Parent\n    - Enfant\n        - Détail\n- Retour"];
        yield 'nested list with a hard break in a continuation' => ["* Parent :  \n    Suite\n    * Enfant\n* Retour"];
        yield 'star marker inside a star list item' => ["* Départ\n* * Étape imbriquée"];
        yield 'list with three spaces after marker' => ["-   Départ\n-   Retour"];
        yield 'star rating stays literal' => ['Hôtel 3*/4* pour le trajet.'];
        yield 'spaced star rating stays literal' => ['Hôtel 3* / 4* pour le trajet.'];
        yield 'star rating range stays literal' => ['Hôtel 2* à 4* selon la disponibilité.'];
        yield 'stars after hotel name stay literal' => ["- Nuit en hôtel***\n- Retour le matin"];
        yield 'four stars after hotel rating stay literal' => ['Nuit en hôtel 4****.'];
        yield 'emphasis ending in a digit' => ['La *Via 1* reste ouverte.'];
        yield 'emphasis ending in a digit after hotel' => ['L’hôtel est proche de la *Via 1* et du départ.'];
        yield 'escaped dot in heading' => ['## 1\\. La première étape'];
        yield 'ordered list with extra marker spaces' => ["1.  Départ\n2.  Retour"];
        yield 'list continuation with four spaces' => ["* Départ :  \n    Rendez-vous à 8 h\n* Retour"];
        yield 'star list lazy continuation' => ["* Départ\nRendez-vous à 8 h\n* Retour"];
        yield 'empty star list item' => ["* Départ\n*"];
        yield 'loose list with spaced markers' => ["-   Départ  \n    \n-   Retour"];
        yield 'list with indented code block' => ["- Départ\n    \n      chemin A\n      chemin B\n- Retour"];
        yield 'list with blank lines inside indented code' => ["- Départ\n    \n      chemin A\n      \n      chemin B\n- Retour"];
        yield 'loose list with image paragraph and caption' => ["-   Un départ\n    \n    ![carte](/media/no_such_image.jpg)\n    \n    Une légende\n    \n-   Un retour"];
        yield 'empty link' => ['Voir []() pour les conditions.'];
        yield 'incomplete link stays literal' => ['Voir [la carte](/incomplete'];
        yield 'link destination with parentheses' => ['Voir [Naxos]((/cyclades)) et [la Crète](/crete).'];
        yield 'link destination with escaped parentheses' => ['Voir [la carte](/carte\\(2\\)).'];
        yield 'phone number as link label' => ['Contactez le [04 76 95 23 09](/contact).'];
        yield 'literal less-than followed by space' => ['Distance < 10 km.'];
        yield 'escaped blockquote marker in paragraph' => ['Départ \\> arrivée.'];
        yield 'hotel ratings following emphasis' => ['- **Accommodation** : 2* or 3* hotel'];
        yield 'literal spaced triple stars in a list' => ["- Un hébergement *** familial\n- Un retour"];
        yield 'numeric underscore stays literal' => ['Étape de 5_h de marche, 15km, +/-450m. Temps de transfert: 1h_'];
        yield 'table with a struck-through price' => ["| Offre | Prix |\n| --- | --- |\n| Départ | ~~200 EUR~~ 150 EUR |"];
        yield 'incomplete image after a complete image' => ['![](missing.jpg)![](incomplete'];
        yield 'image destination with spaces stays literal' => ['![carte](ATR MEMBRE - COULEUR.png)'];
        yield 'escaped brackets in emphasis' => ['Lisez _\\[note\\]_ avant le départ.'];
        yield 'single tilde strikethrough' => ['Réduction ~30€~ pour le trajet.'];
        yield 'two approximate quantities' => ['Distance ~170 km et dénivelé ~10 000 m.'];
        yield 'hard line breaks' => ["Première ligne  \nDeuxième ligne  \nTroisième ligne."];
        yield 'heading with extra spaces' => ['##  Conseils pratiques'];
        yield 'heading with an inline span' => ['## <span id="cookies">Cookies</span>'];
        yield 'empty heading' => ["##   \nLa suite du texte."];
        yield 'trailing space in link destination' => ['Voir [la carte](/carte ).'];
        yield 'inline HTML and entity' => ['Prix <span data-price-eur="2">2&nbsp;€</span> & transport.'];
        yield 'inline HTML comment' => ['Une marche <!-- todo: check route --> en montagne.'];
        yield 'escaped asterisk' => ['Une marche\\* en montagne.'];
        yield 'escaped underscore' => ['La clé axeptio\\_cookies reste littérale.'];
        yield 'escaped star rating' => ['Hôtel 3\\*\\* pour le trajet.'];
        yield 'missing image' => ['![carte](/media/no-such-image.jpg)'];
        yield 'missing image with underscores in filename' => ['![carte](/media/no_such_image.jpg)'];
        yield 'missing image in a sentence' => ['Voir ![carte](/media/no_such_image.jpg) avant le départ.'];
        yield 'empty alt image in a sentence' => ['Voir ![](/media/no_such_image.jpg) avant le départ.'];
        yield 'image with parentheses in filename' => ['Voir ![carte](/media/no_such_image(2).jpg) avant le départ.'];
        yield 'full image with underscores in alt' => ['![carte_du_trajet](/media/no_such_image.jpg)'];
        yield 'missing image in a link' => ['[![carte](/media/no_such_image.jpg)](/carte)'];
        yield 'missing image in a titled link' => ['[![carte](/media/no_such_image.jpg)](/carte "Carte du trajet")'];
        yield 'linked image with underscore alt' => ['[![carte_du_trajet](/media/no_such_image.jpg)](/carte)'];
        yield 'obfuscated mail link with phone' => ['Appelez le 07 69 44 78 66 ou écrivez à #[bonjour@example.com](mailto:bonjour@example.com).'];
        yield 'obfuscated mail link' => ['Écrivez à #[bonjour@example.com](mailto:bonjour@example.com).'];
        yield 'obfuscated link and plain email' => ['Voir #[notre équipe](/equipe) ou écrire à bonjour@example.com.'];
        yield 'emphasis followed by bold' => ['_Note_ : Une **marche facile**.'];
        yield 'underscore in attributed link destination' => ['#[Le guide](https://example.com/page?menu_13000_kcal){target="_blank"}'];
    }

    #[DataProvider('compatibleDynamicSources')]
    public function testDynamicOutputMatchesCommonMark(string $source): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $renderer = new TempestMarkdownRenderer($container->get(LinkProvider::class), $container->get(SiteRegistry::class), $container->get(Twig::class), $container->get(MediaExtension::class));
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new AttributesExtension());
        $environment->addExtension(new StrikethroughExtension());
        $environment->addExtension(new TableExtension());
        $environment->addExtension(new TaskListExtension());
        $environment->addExtension(new PushwordExtension($container->get(LinkProvider::class), $container->get(MediaExtension::class), $container->get(SiteRegistry::class), new Date($container->get(SiteRegistry::class))));
        $environment->addExtension(new NoticeExtension($container->get(Twig::class), $container->get(SiteRegistry::class)));

        $converter = new MarkdownConverter($environment);

        $actual = $renderer->render($source);
        self::assertNotNull($actual);
        $locale = $container->get(SiteRegistry::class)->getLocale();
        $typographer = new Typographer();
        self::assertSame(
            HtmlEquivalence::structure($typographer->fix($converter->convert($source)->__toString(), $locale)),
            HtmlEquivalence::structure($typographer->fix($actual, $locale)),
        );
    }

    public function testAttributeOrderIsIrrelevantAfterTheContentFilters(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $page = new Page();
        $page->host = 'localhost.dev';
        $page->locale = 'fr';

        $manager = $container->get(ContentPipelineFactory::class)->get($page)->getLegacyManager();
        $filters = $container->get(FilterRegistry::class);

        $render = static function (string $html) use ($filters, $manager, $page): string {
            foreach (\array_slice(Configuration::DEFAULT_FILTERS['main_content'], 3) as $name) {
                $filter = $filters->getFilter($name);
                self::assertNotNull($filter);
                $html = $filter->apply($html, $page, $manager, 'MainContent');
                self::assertIsString($html);
            }

            return $html;
        };

        self::assertSame(
            HtmlEquivalence::structure($render('<p>l\'histoire <a class="guide" href="/marche">marche</a>.</p>')),
            HtmlEquivalence::structure($render('<p>l\'histoire <a href="/marche" class="guide">marche</a>.</p>')),
        );
        self::assertNotSame(
            HtmlEquivalence::structure($render("<p>l'histoire</p>")),
            HtmlEquivalence::structure($render('<p>l&#x27;histoire</p>')),
        );
    }

    public function testDefaultParserUsesTempestForMalformedEmphasis(): void
    {
        self::bootKernel();

        $html = self::getContainer()->get(MarkdownParser::class)->transform('pain**, mais les** horaires');

        self::assertSame('<p>pain<strong>, mais les</strong> horaires</p>', trim($html));
    }
}
