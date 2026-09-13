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
    }

    #[DataProvider('compatibleDynamicSources')]
    public function testDynamicOutputMatchesCommonMark(string $source): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $renderer = new TempestMarkdownRenderer($container->get(LinkProvider::class), $container->get(SiteRegistry::class), $container->get(Twig::class));
        $converter = new ReflectionProperty(MarkdownParser::class, 'converter')->getValue($container->get(MarkdownParser::class));
        self::assertInstanceOf(MarkdownConverter::class, $converter);

        self::assertSame($converter->convert($source)->__toString(), $renderer->render($source));
    }

    public function testLooseNoticeListFallsBackToCommonMark(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $renderer = new TempestMarkdownRenderer($container->get(LinkProvider::class), $container->get(SiteRegistry::class), $container->get(Twig::class));

        self::assertNull($renderer->render("> [!faq] Équipement\n>\n> - Une veste\n>\n> - Un sac"));
    }
}
