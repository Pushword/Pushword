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
        yield 'paragraph followed by heading' => ["Une journée en montagne.\n## Le retour"];
        yield 'empty table header' => ["| | |\n|---|---|\n| x | y |"];
        yield 'table row trailing space' => ["| A | B |\n|---|---|\n| x | y | "];
        yield 'titled link' => ['Voir [la carte](/carte "Carte du trajet").'];
        yield 'table colspan and short row' => ["| Offre | Deux | Trois |\n| --- | --- | --- |\n| Location | 25 € | -> |\n| Assurance | 10 € |"];
        yield 'empty table header with colspan' => ["| | | -> |\n| --- | --- | --- |\n| | Prix | -> |"];
        yield 'three-level list' => ["- Parent\n    - Enfant\n        - Détail\n- Retour"];
        yield 'star rating stays literal' => ['Hôtel 3*/4* pour le trajet.'];
        yield 'escaped brackets in emphasis' => ['Lisez _\\[note\\]_ avant le départ.'];
        yield 'single tilde strikethrough' => ['Réduction ~30€~ pour le trajet.'];
        yield 'two approximate quantities' => ['Distance ~170 km et dénivelé ~10 000 m.'];
        yield 'trailing space in link destination' => ['Voir [la carte](/carte ).'];
        yield 'inline HTML and entity' => ['Prix <span data-price-eur="2">2&nbsp;€</span> & transport.'];
        yield 'inline HTML comment' => ['Une marche <!-- todo: check route --> en montagne.'];
        yield 'escaped asterisk' => ['Une marche\\* en montagne.'];
        yield 'missing image' => ['![carte](/media/no-such-image.jpg)'];
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
