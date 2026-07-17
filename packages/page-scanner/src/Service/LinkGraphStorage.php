<?php

namespace Pushword\PageScanner\Service;

use function Safe\json_decode;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Reads and writes the link-graph snapshots `pw:page-scan` leaves behind, next to
 * the error cache it already writes.
 *
 * A link graph is always scoped to one host: a page earns its links from its own
 * site, and mixing hosts in one report answers a question nobody asks. So an
 * all-hosts scan writes one snapshot per host rather than a single combined one,
 * and `pw:link:graph <host>` finds its scope whichever way the scan was run.
 *
 * It stores the raw nodes and edges rather than a built report, so the report can
 * change shape without a rescan, and stores the scanned node list rather than
 * re-querying at read time, so a page published after the scan cannot show up as
 * an orphan that was never rendered. Written as JSON (not serialize() like its
 * neighbour) because a graph is worth inspecting by hand.
 */
final readonly class LinkGraphStorage
{
    public function __construct(
        private Filesystem $filesystem,
        private string $varDir,
    ) {
    }

    /**
     * Split a scan's nodes by host and write one snapshot each. An edge travels
     * with its source's host; a cross-host target is simply not a node there, so
     * {@see LinkGraphBuilder} drops it like any other non-page.
     *
     * @param list<string>                $nodes
     * @param array<string, list<string>> $edges
     */
    public function writeAll(array $nodes, array $edges): void
    {
        $nodesByHost = [];
        foreach ($nodes as $node) {
            $nodesByHost[explode('/', $node, 2)[0]][] = $node;
        }

        foreach ($nodesByHost as $host => $hostNodes) {
            $this->write($host, $hostNodes, array_intersect_key($edges, array_flip($hostNodes)));
        }
    }

    /**
     * @param list<string>                $nodes
     * @param array<string, list<string>> $edges
     */
    public function write(string $host, array $nodes, array $edges): void
    {
        $this->filesystem->dumpFile($this->file($host), json_encode([
            'nodes' => $nodes,
            'edges' => $edges,
        ], \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{generatedAt: int, nodes: list<string>, edges: array<string, list<string>>}|null
     *                                                                                               null when the host was never scanned
     */
    public function read(string $host): ?array
    {
        $file = $this->file($host);
        if (! $this->filesystem->exists($file)) {
            return null;
        }

        /** @var array{nodes: list<string>, edges: array<string, list<string>>} $data */
        $data = json_decode($this->filesystem->readFile($file), true);

        return [
            'generatedAt' => (int) filemtime($file),
            'nodes' => $data['nodes'],
            'edges' => $data['edges'],
        ];
    }

    private function file(string $host): string
    {
        return $this->varDir.'/page-scan-graph--'.$host;
    }
}
