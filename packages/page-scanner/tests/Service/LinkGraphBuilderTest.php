<?php

namespace Pushword\PageScanner\Tests\Service;

use PHPUnit\Framework\TestCase;
use Pushword\PageScanner\Service\LinkGraphBuilder;

final class LinkGraphBuilderTest extends TestCase
{
    /**
     * @param list<array{host: string, slug: string, inboundCount: int, outboundCount: int, inbound: list<string>, depth: int|null}> $pages
     *
     * @return array{host: string, slug: string, inboundCount: int, outboundCount: int, inbound: list<string>, depth: int|null}
     */
    private function page(array $pages, string $node): array
    {
        foreach ($pages as $page) {
            if ($page['host'].'/'.$page['slug'] === $node) {
                return $page;
            }
        }

        self::fail($node.' is not in the graph');
    }

    /**
     * The orphan set, named the way callers derive it: by filtering the pages.
     *
     * @param array{pages: list<array{host: string, slug: string, inboundCount: int, outboundCount: int, inbound: list<string>, depth: int|null}>, ...} $graph
     *
     * @return list<string>
     */
    private function orphans(array $graph): array
    {
        return array_values(array_map(
            static fn (array $page): string => $page['host'].'/'.$page['slug'],
            array_filter($graph['pages'], LinkGraphBuilder::isOrphan(...)),
        ));
    }

    public function testCountsInboundAndOutbound(): void
    {
        $graph = new LinkGraphBuilder()->build(
            ['a.tld/homepage', 'a.tld/one', 'a.tld/two'],
            [
                'a.tld/homepage' => ['a.tld/one', 'a.tld/two'],
                'a.tld/one' => ['a.tld/two'],
            ],
        );

        self::assertSame(3, $graph['pageCount']);
        self::assertSame(3, $graph['edgeCount']);

        $two = $this->page($graph['pages'], 'a.tld/two');
        self::assertSame(2, $two['inboundCount']);
        self::assertSame(0, $two['outboundCount']);
        self::assertSame(['a.tld/homepage', 'a.tld/one'], $two['inbound']);
    }

    public function testDropsEdgesToNonPages(): void
    {
        $graph = new LinkGraphBuilder()->build(
            ['a.tld/homepage'],
            ['a.tld/homepage' => ['a.tld/media/photo.jpg', 'a.tld/admin', 'external.tld/homepage']],
        );

        self::assertSame(0, $graph['edgeCount']);
        self::assertSame(0, $this->page($graph['pages'], 'a.tld/homepage')['outboundCount']);
    }

    public function testEdgesFromAnUnscannedSourceAreIgnored(): void
    {
        $graph = new LinkGraphBuilder()->build(
            ['a.tld/homepage'],
            ['a.tld/ghost' => ['a.tld/homepage']],
        );

        self::assertSame(0, $graph['edgeCount']);
        self::assertSame(0, $this->page($graph['pages'], 'a.tld/homepage')['inboundCount']);
    }

    public function testDepthWalksFromTheHomepage(): void
    {
        $graph = new LinkGraphBuilder()->build(
            ['a.tld/homepage', 'a.tld/one', 'a.tld/two', 'a.tld/lost'],
            [
                'a.tld/homepage' => ['a.tld/one'],
                'a.tld/one' => ['a.tld/two'],
                'a.tld/lost' => ['a.tld/homepage'],
            ],
        );

        self::assertSame(0, $this->page($graph['pages'], 'a.tld/homepage')['depth']);
        self::assertSame(1, $this->page($graph['pages'], 'a.tld/one')['depth']);
        self::assertSame(2, $this->page($graph['pages'], 'a.tld/two')['depth']);
        self::assertNull($this->page($graph['pages'], 'a.tld/lost')['depth'], 'linking TO the home does not make a page reachable FROM it');
    }

    public function testDepthKeepsTheShortestPath(): void
    {
        $graph = new LinkGraphBuilder()->build(
            ['a.tld/homepage', 'a.tld/hub', 'a.tld/leaf'],
            [
                'a.tld/homepage' => ['a.tld/hub', 'a.tld/leaf'],
                'a.tld/hub' => ['a.tld/leaf'],
            ],
        );

        self::assertSame(1, $this->page($graph['pages'], 'a.tld/leaf')['depth']);
    }

    public function testALocaleHomeIsNotARoot(): void
    {
        $graph = new LinkGraphBuilder()->build(
            ['a.tld/homepage', 'a.tld/fr/homepage', 'a.tld/fr/one'],
            [
                'a.tld/homepage' => ['a.tld/fr/homepage'],
                'a.tld/fr/homepage' => ['a.tld/fr/one'],
            ],
        );

        self::assertSame(1, $this->page($graph['pages'], 'a.tld/fr/homepage')['depth']);
        self::assertSame(2, $this->page($graph['pages'], 'a.tld/fr/one')['depth'], 'a locale home must not root its own walk');
    }

    public function testDepthCrossesHosts(): void
    {
        $graph = new LinkGraphBuilder()->build(
            ['a.tld/homepage', 'b.tld/one'],
            ['a.tld/homepage' => ['b.tld/one']],
        );

        self::assertSame(1, $this->page($graph['pages'], 'b.tld/one')['depth']);
    }

    public function testOrphansAreThePagesLinkedOnceOrLess(): void
    {
        $graph = new LinkGraphBuilder()->build(
            ['a.tld/homepage', 'a.tld/once', 'a.tld/never', 'a.tld/twice'],
            [
                'a.tld/homepage' => ['a.tld/once', 'a.tld/twice'],
                'a.tld/once' => ['a.tld/twice'],
            ],
        );

        self::assertSame(['a.tld/never', 'a.tld/once'], $this->orphans($graph));
        self::assertSame(2, $graph['orphanCount']);
    }

    public function testTheHomepageIsNeverAnOrphan(): void
    {
        // Nothing links back to the home: it is still where visitors land.
        $graph = new LinkGraphBuilder()->build(['a.tld/homepage', 'a.tld/one'], ['a.tld/homepage' => ['a.tld/one']]);

        self::assertSame(['a.tld/one'], $this->orphans($graph));
    }

    public function testALocaleHomeCanBeAnOrphan(): void
    {
        $graph = new LinkGraphBuilder()->build(['a.tld/homepage', 'a.tld/fr/homepage'], []);

        self::assertSame(['a.tld/fr/homepage'], $this->orphans($graph));
    }

    public function testLeastLinkedPagesComeFirst(): void
    {
        $graph = new LinkGraphBuilder()->build(
            ['a.tld/homepage', 'a.tld/one', 'a.tld/two'],
            [
                'a.tld/homepage' => ['a.tld/one', 'a.tld/two'],
                'a.tld/one' => ['a.tld/two'],
            ],
        );

        self::assertSame(
            ['a.tld/homepage', 'a.tld/one', 'a.tld/two'],
            array_map(static fn (array $page): string => $page['host'].'/'.$page['slug'], $graph['pages']),
        );
    }

    public function testFlagsAGraphWhoseHomepageWasNeverScanned(): void
    {
        $graph = new LinkGraphBuilder()->build(['a.tld/one', 'a.tld/two'], []);

        // Depth is null there by absence of a root, not by a linking problem.
        self::assertFalse($graph['homepageScanned']);
        self::assertNull($this->page($graph['pages'], 'a.tld/one')['depth']);
    }

    public function testAScannedHomepageIsFlagged(): void
    {
        self::assertTrue(new LinkGraphBuilder()->build(['a.tld/homepage'], [])['homepageScanned']);
    }

    public function testALocaleHomeDoesNotCountAsTheHomepage(): void
    {
        // Same slug-exactness rule the walk roots on: `fr/homepage` is not a root.
        self::assertFalse(new LinkGraphBuilder()->build(['a.tld/fr/homepage'], [])['homepageScanned']);
    }
}
