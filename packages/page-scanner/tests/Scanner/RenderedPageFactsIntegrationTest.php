<?php

declare(strict_types=1);

namespace Pushword\PageScanner\Tests\Scanner;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\PageScanner\Scanner\LinkedDocsScanner;
use Pushword\PageScanner\Scanner\LinkGraphScanner;
use Pushword\PageScanner\Scanner\MissingAltScanner;
use Pushword\PageScanner\Scanner\RenderedPageFacts;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class RenderedPageFactsIntegrationTest extends KernelTestCase
{
    public function testInjectedFactsPreserveFindingsAndGraphWithoutLeakingToTheNextPage(): void
    {
        self::bootKernel();
        $page = new Page();
        $page->host = 'localhost.dev';
        $page->slug = 'source';
        $page->locale = 'en';

        $html = '<div id="found"></div><a href="#found">yes</a><a href="#missing">no</a>'
            .'<a href="/other">other</a><img src="/lake.jpg"><img src="/lake.jpg">';
        $facts = new RenderedPageFacts(['#found', '#missing', '/other'], ['/lake.jpg'], ['found']);

        /** @var LinkedDocsScanner $links */
        $links = self::getContainer()->get(LinkedDocsScanner::class);
        /** @var MissingAltScanner $images */
        $images = self::getContainer()->get(MissingAltScanner::class);
        /** @var LinkGraphScanner $graph */
        $graph = self::getContainer()->get(LinkGraphScanner::class);

        $expectedLinks = $links->scan($page, $html);
        $expectedImages = $images->scan($page, $html);
        $graph->scan($page, $html);
        $expectedGraph = $graph->getEdges();

        self::assertSame($expectedLinks, $links->scan($page, $html, $facts));
        self::assertSame($expectedImages, $images->scan($page, $html, $facts));
        $graph->reset();
        $graph->scan($page, $html, $facts);
        self::assertSame($expectedGraph, $graph->getEdges());

        self::assertSame($expectedLinks, $links->scan($page, $html));
        self::assertSame($expectedImages, $images->scan($page, $html));
        $graph->reset();
        $graph->scan($page, $html);
        self::assertSame($expectedGraph, $graph->getEdges());
    }
}
