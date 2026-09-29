<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Component\EntityFilter\Filter;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Component\EntityFilter\Filter\Markdown;
use Pushword\Core\Component\EntityFilter\FilterRegistry;
use Pushword\Core\Component\EntityFilter\Manager;
use Pushword\Core\Content\ContentPipeline;
use Pushword\Core\Content\ContentPipelineFactory;
use Pushword\Core\Entity\Page;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class MarkdownPlainBlockTest extends KernelTestCase
{
    public function testPlainBlocksDoNotRequireTheTwigFilter(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $factory = $container->get(ContentPipelineFactory::class);
        $filter = $container->get(FilterRegistry::class)->getFilter('markdown');
        self::assertInstanceOf(Markdown::class, $filter);

        $page = new Page();
        $page->host = 'localhost';
        $page->locale = 'en';

        $manager = new Manager(new ContentPipeline(
            $factory,
            $factory->eventDispatcher,
            new FilterRegistry([]),
            $page,
            $factory->apps,
        ));

        $html = $filter->apply("A **plain** paragraph.\n\n- First\n- Second", $page, $manager);

        self::assertIsString($html);
        self::assertStringContainsString('<strong>plain</strong>', $html);
        self::assertStringContainsString('<li>Second</li>', $html);
    }

    public function testTwigAndCodeBlocksStillUseTheProtectedPath(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $factory = $container->get(ContentPipelineFactory::class);
        $filter = $container->get(FilterRegistry::class)->getFilter('markdown');
        self::assertInstanceOf(Markdown::class, $filter);

        $page = new Page();
        $page->host = 'localhost';
        $page->locale = 'en';

        $manager = $factory->getLegacyManager($page);

        $html = $filter->apply("Value: {{ 1 + 1 }}.\n\n`{{ 2 + 2 }}`\n\n<pre>{{ 3 + 3 }}</pre>\n\n## Heading {#heading}", $page, $manager);

        self::assertIsString($html);
        self::assertStringContainsString('Value: 2.', $html);
        self::assertStringContainsString('<code>{{ 2 + 2 }}</code>', $html);
        self::assertStringContainsString('<pre>{{ 3 + 3 }}</pre>', $html);
        self::assertStringContainsString('id="heading"', $html);
    }

    public function testSameLineBlockAttributesRenderMarkdown(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $factory = $container->get(ContentPipelineFactory::class);
        $filter = $container->get(FilterRegistry::class)->getFilter('markdown');
        self::assertInstanceOf(Markdown::class, $filter);

        $page = new Page();
        $page->host = 'localhost';
        $page->locale = 'fr';

        $html = $filter->apply("{.ico-tip} N’hésitez pas à voir les **photos**.\n\n{#photo-tip} Regardez les *images*.\n\n{.note #more} Les **détails**.\n\n{data-role=\"note\"} Un *conseil*.", $page, $factory->getLegacyManager($page));

        self::assertIsString($html);
        self::assertStringContainsString('<p class="ico-tip">N’hésitez pas à voir les <strong>photos</strong>.</p>', $html);
        self::assertStringContainsString('<p id="photo-tip">Regardez les <em>images</em>.</p>', $html);
        self::assertStringContainsString('<p class="note" id="more">Les <strong>détails</strong>.</p>', $html);
        self::assertStringContainsString('<p data-role="note">Un <em>conseil</em>.</p>', $html);

        $raw = $filter->apply('{literal} **unparsed**', $page, $factory->getLegacyManager($page));
        self::assertSame("{literal} **unparsed**\n\n", $raw);
    }

    public function testSameLineAttributeDetectionSparesTwigAndLiteralBraces(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $factory = $container->get(ContentPipelineFactory::class);
        $filter = $container->get(FilterRegistry::class)->getFilter('markdown');
        self::assertInstanceOf(Markdown::class, $filter);

        $page = new Page();
        $page->host = 'localhost';
        $page->locale = 'fr';

        $render = static function (string $source) use ($filter, $page, $factory): string {
            $html = $filter->apply($source, $page, $factory->getLegacyManager($page));
            self::assertIsString($html);

            return $html;
        };

        // A block opening on a Twig tag or a social handle is not attributed: it stays raw.
        self::assertSame(" Texte\n\n", $render('{# commentaire #} Texte'));
        self::assertSame("x texte\n\n", $render("{{ 'x' }} texte"));
        self::assertSame("oui texte\n\n", $render('{% if true %}oui{% endif %} texte'));
        self::assertSame("{x:example} texte\n\n", $render('{x:example} texte'));

        self::assertStringContainsString('<p class="a">Twig évalué</p>', $render("{.a} {{ 'Twig' }} évalué"));
        self::assertStringContainsString('<p class="a">Texte</p>', $render("{.a}\tTexte"));
        self::assertStringContainsString("<blockquote>\n<p class=\"a\">Citation</p>\n</blockquote>", $render('> {.a} Citation'));
        self::assertStringContainsString('<p class="a"># Titre</p>', $render('{.a} # Titre'));

        self::assertStringContainsString('<p><code>{.a}</code> Texte</p>', $render('`{.a}` Texte'));
        self::assertStringContainsString('<p>{.a} Texte</p>', $render('\\{.a} Texte'));
    }
}
