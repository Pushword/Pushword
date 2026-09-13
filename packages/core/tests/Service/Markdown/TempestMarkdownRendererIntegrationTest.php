<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Service\Markdown;

use League\CommonMark\MarkdownConverter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Service\LinkProvider;
use Pushword\Core\Service\Markdown\MarkdownParser;
use Pushword\Core\Service\Markdown\TempestMarkdownRenderer;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Twig\MediaExtension;
use ReflectionProperty;
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
        yield 'encoded email' => ['Écrivez à bonjour@example.com.'];
        yield 'phone' => ['Appelez le 07 69 44 78 66.'];
        yield 'date shortcode' => ['Rendez-vous en date(Y).'];
        yield 'short notice' => ["> [!faq] Une question ?\n>\n> Une réponse courte."];
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
        $converter = new ReflectionProperty(MarkdownParser::class, 'converter')->getValue($container->get(MarkdownParser::class));
        self::assertInstanceOf(MarkdownConverter::class, $converter);

        self::assertSame($converter->convert($source)->__toString(), $renderer->render($source));
    }
}
