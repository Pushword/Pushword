<?php

declare(strict_types=1);

namespace Pushword\PageScanner\Tests\Scanner;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\LinkProvider;
use Pushword\PageScanner\Scanner\DateShortcodeScanner;
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

        $obfuscated = LinkProvider::obfuscate('/hidden');
        $html = '<div id="found"></div><a href="#found">yes</a><a href="#missing">no</a>'
            .'<a href="/other">other</a><img src="/lake.jpg"><img src="/lake.jpg" srcset="/other.jpg 1x">'
            .'<span data-rot="'.$obfuscated.'">hidden</span><div data-bg="/background.jpg"></div>'
            .'<code>&lt;a href="/example-only"&gt;example&lt;/a&gt; date(M)</code>'
            .'<meta name="description" content="date(Y)">';
        $facts = new RenderedPageFacts(
            ['#found', '#missing', '/other'],
            ['/lake.jpg'],
            ['found'],
            [
                ['name' => 'href', 'value' => '#found'],
                ['name' => 'href', 'value' => '#missing'],
                ['name' => 'href', 'value' => '/other'],
                ['name' => 'src', 'value' => '/lake.jpg'],
                ['name' => 'src', 'value' => '/lake.jpg'],
                ['name' => 'data-rot', 'value' => $obfuscated],
                ['name' => 'data-bg', 'value' => '/background.jpg'],
            ],
            ['/other.jpg 1x'],
            ['date(Y)'],
        );

        /** @var LinkedDocsScanner $links */
        $links = self::getContainer()->get(LinkedDocsScanner::class);
        /** @var MissingAltScanner $images */
        $images = self::getContainer()->get(MissingAltScanner::class);
        /** @var LinkGraphScanner $graph */
        $graph = self::getContainer()->get(LinkGraphScanner::class);
        /** @var DateShortcodeScanner $dates */
        $dates = self::getContainer()->get(DateShortcodeScanner::class);

        $expectedLinks = $links->scan($page, $html);
        $expectedImages = $images->scan($page, $html);
        $expectedDates = $dates->scan($page, $html);
        $graph->scan($page, $html);
        $expectedGraph = $graph->getEdges();

        self::assertSame($expectedLinks, $links->scan($page, $html, $facts));
        self::assertSame($expectedImages, $images->scan($page, $html, $facts));
        self::assertSame($expectedDates, $dates->scan($page, $html, $facts));
        $withoutDates = new RenderedPageFacts($facts->hrefs, $facts->missingAlt, $facts->anchors, $facts->linkedAttributes, $facts->srcsets, []);
        self::assertSame([], $dates->scan($page, $html, $withoutDates));
        $graph->reset();
        $graph->scan($page, $html, $facts);
        self::assertSame($expectedGraph, $graph->getEdges());

        self::assertSame($expectedLinks, $links->scan($page, $html));
        self::assertSame($expectedImages, $images->scan($page, $html));
        self::assertSame($expectedDates, $dates->scan($page, $html));
        $graph->reset();
        $graph->scan($page, $html);
        self::assertSame($expectedGraph, $graph->getEdges());
    }
}
