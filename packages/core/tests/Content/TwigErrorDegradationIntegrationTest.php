<?php

namespace Pushword\Core\Tests\Content;

use Pushword\Core\Content\ContentPipelineFactory;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\EditorNotice\TwigErrorMarker;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Renders broken Twig through the real content pipeline (filters + CommonMark) to
 * prove the whole chain degrades one bad block to an invisible marker — reaching
 * the final HTML instead of throwing a 500 — while neighbouring blocks survive.
 */
final class TwigErrorDegradationIntegrationTest extends KernelTestCase
{
    public function testBrokenTwigInMainContentDegradesToAMarkerInsteadOfThrowing(): void
    {
        self::bootKernel();

        /** @var ContentPipelineFactory $factory */
        $factory = self::getContainer()->get(ContentPipelineFactory::class);

        $page = new Page();
        $page->host = 'localhost';
        $page->locale = 'en';
        $page->slug = 'twig-error-demo';
        $page->mainContent = "Good paragraph.\n\nBroken: {{ undefined_function_xyz() }}\n\nAnother good paragraph.";

        $html = $factory->get($page)->getMainContent();

        // The bad block degraded to the invisible marker (survives CommonMark).
        self::assertStringContainsString('pushword:twig-error', $html, $html);
        self::assertNotSame([], TwigErrorMarker::extractMessages($html));

        // One bad block must not take down its neighbours.
        self::assertStringContainsString('Good paragraph.', $html);
        self::assertStringContainsString('Another good paragraph.', $html);
    }

    /**
     * The editor-reachable case: a bare view name resolves to
     * /component/pages_list_<name>.html.twig by convention, and the resolver falls back
     * to @Pushword without checking the file is there. A variant renamed or deleted —
     * or a page flat-synced to a host that never had it — used to escape as a
     * LoaderError and 500 the public page.
     */
    public function testAPagesListNamingAMissingVariantDegradesToAMarkerInsteadOfThrowing(): void
    {
        self::bootKernel();

        /** @var ContentPipelineFactory $factory */
        $factory = self::getContainer()->get(ContentPipelineFactory::class);

        $page = new Page();
        $page->host = 'localhost';
        $page->locale = 'en';
        $page->slug = 'missing-variant-demo';
        $page->mainContent = "Good paragraph.\n\n{{ pages_list('slug:homepage', 10, view: 'noSuchVariant') }}\n\nAnother good paragraph.";

        $html = $factory->get($page)->getMainContent();

        self::assertStringContainsString('pushword:twig-error', $html, $html);
        self::assertNotSame([], TwigErrorMarker::extractMessages($html));

        self::assertStringContainsString('Good paragraph.', $html);
        self::assertStringContainsString('Another good paragraph.', $html);
    }
}
