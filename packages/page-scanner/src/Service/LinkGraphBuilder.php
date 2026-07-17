<?php

namespace Pushword\PageScanner\Service;

/**
 * Turns the raw edges collected during a scan into the link-graph report:
 * inbound/outbound counts, inbound sources, depth from the homepage, orphans.
 *
 * Known limit, intentional. Rendered offline, pages_list() only ever renders its
 * first pager page (RequestContext::$currentPager defaults to 1 and nothing sets
 * it outside a request), so the graph is the graph reachable *without
 * paginating*. That is exactly what `orphans` means here — a page should always
 * be linked without a pager — but it makes `inboundCount` a lower bound and
 * `depth` an upper bound for pages only listed on pager 2+.
 *
 * @phpstan-type LinkGraphPage array{host: string, slug: string, inboundCount: int, outboundCount: int, inbound: list<string>, depth: int|null}
 * @phpstan-type LinkGraph array{
 *     pageCount: int,
 *     edgeCount: int,
 *     orphanCount: int,
 *     orphans: list<string>,
 *     hostsWithoutHomepage: list<string>,
 *     pages: list<LinkGraphPage>,
 * }
 */
final class LinkGraphBuilder
{
    /** A page linked once or less is not meaningfully linked. */
    public const int ORPHAN_MAX_INBOUND = 1;

    /**
     * @param list<string>                $nodes a `host/slug` per scanned page
     * @param array<string, list<string>> $edges source `host/slug` => target `host/slug`[]
     *
     * @return LinkGraph
     */
    public function build(array $nodes, array $edges): array
    {
        $known = array_fill_keys($nodes, true);

        $inbound = array_fill_keys($nodes, []);
        $outbound = array_fill_keys($nodes, []);
        $edgeCount = 0;

        foreach ($edges as $source => $targets) {
            if (! isset($known[$source])) {
                continue;
            }

            foreach ($targets as $target) {
                if (! isset($known[$target])) {
                    continue; // media, static file, dead link, unscanned page
                }

                $outbound[$source][] = $target;
                $inbound[$target][] = $source;
                ++$edgeCount;
            }
        }

        $depth = $this->computeDepth($nodes, $outbound);

        $pages = [];
        foreach ($nodes as $node) {
            [$host, $slug] = explode('/', $node, 2);

            $pages[] = [
                'host' => $host,
                'slug' => $slug,
                'inboundCount' => \count($inbound[$node]),
                'outboundCount' => \count($outbound[$node]),
                'inbound' => $inbound[$node],
                'depth' => $depth[$node] ?? null,
            ];
        }

        // Least-linked first: the reason anyone reads this report.
        usort($pages, static fn (array $a, array $b): int => [$a['inboundCount'], $a['host'], $a['slug']]
            <=> [$b['inboundCount'], $b['host'], $b['slug']]);

        $orphans = array_map(
            static fn (array $page): string => $page['host'].'/'.$page['slug'],
            array_filter($pages, self::isOrphan(...)),
        );

        return [
            'pageCount' => \count($nodes),
            'edgeCount' => $edgeCount,
            'orphanCount' => \count($orphans),
            'orphans' => array_values($orphans),
            'hostsWithoutHomepage' => $this->hostsWithoutHomepage($nodes),
            'pages' => $pages,
        ];
    }

    /**
     * A homepage is reachable by definition — it is where visitors land, and it
     * is the walk's root. Only a page you can fail to reach can be an orphan, so
     * the root never is, however few links point back to it. A locale home
     * (slug `fr/homepage`) is not a root and has to earn its links like any page.
     *
     * @param LinkGraphPage $page
     */
    public static function isOrphan(array $page): bool
    {
        return 'homepage' !== $page['slug'] && $page['inboundCount'] <= self::ORPHAN_MAX_INBOUND;
    }

    /**
     * A host whose homepage was never scanned has no root to walk from, so every
     * depth it reports is null by absence, not by structure. Naming it keeps that
     * from reading as a corpus-wide linking problem.
     *
     * @param list<string> $nodes
     *
     * @return list<string>
     */
    private function hostsWithoutHomepage(array $nodes): array
    {
        $hosts = [];
        $withHomepage = [];
        foreach ($nodes as $node) {
            [$host, $slug] = explode('/', $node, 2);
            $hosts[$host] = true;
            if ('homepage' === $slug) {
                $withHomepage[$host] = true;
            }
        }

        return array_keys(array_diff_key($hosts, $withHomepage));
    }

    /**
     * Breadth-first from every host's homepage at once, so a page gets its
     * shortest distance from any home (cross-host links included). Pages never
     * reached stay absent, and surface as a null depth.
     *
     * Only a page whose slug is exactly `homepage` is a root. A locale home
     * (slug `fr/homepage`) is a page like any other: it is reached from the
     * home, and rooting the walk there too would shift its whole locale one
     * level closer than a visitor ever finds it.
     *
     * @param list<string>                $nodes
     * @param array<string, list<string>> $outbound
     *
     * @return array<string, int>
     */
    private function computeDepth(array $nodes, array $outbound): array
    {
        $depth = [];
        $queue = [];
        foreach ($nodes as $node) {
            if ('homepage' === explode('/', $node, 2)[1]) {
                $depth[$node] = 0;
                $queue[] = $node;
            }
        }

        // isset(), not $i < count($queue): the body appends to $queue, so the
        // bound must stay live. A hoisted count() would stop the walk at depth 1.
        for ($i = 0; isset($queue[$i]); ++$i) {
            $current = $queue[$i];
            foreach ($outbound[$current] as $target) {
                if (isset($depth[$target])) {
                    continue;
                }

                $depth[$target] = $depth[$current] + 1;
                $queue[] = $target;
            }
        }

        return $depth;
    }
}
