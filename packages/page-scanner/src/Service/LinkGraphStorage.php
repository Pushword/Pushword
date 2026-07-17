<?php

namespace Pushword\PageScanner\Service;

use Pushword\Core\Repository\PageRepository;

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
 *
 * Each snapshot also carries the corpus state it was taken from, so `stale` is a
 * fact rather than a guess about the file's age: a graph is whole-corpus, and one
 * page's edit moves *other* pages' inbound counts, so nothing about a page can tell
 * you its own numbers are still good. Whoever writes a snapshot is the one that
 * knows which corpus it describes, which is why write() demands the state instead
 * of reading it back here — the scan reads it *before* rendering, so an edit
 * landing mid-scan reads as stale afterwards rather than as scanned.
 *
 * @phpstan-import-type CorpusState from PageRepository
 */
final readonly class LinkGraphStorage
{
    public function __construct(
        private Filesystem $filesystem,
        private PageRepository $pageRepo,
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
     * @param array<string, CorpusState>  $corpusByHost the state each host's corpus was in when the scan started
     */
    public function writeAll(array $nodes, array $edges, array $corpusByHost): void
    {
        $nodesByHost = [];
        foreach ($nodes as $node) {
            $nodesByHost[explode('/', $node, 2)[0]][] = $node;
        }

        foreach ($nodesByHost as $host => $hostNodes) {
            // Every node came from the corpus the map was built from, so a node host
            // is always a key here.
            $this->write($host, $hostNodes, array_intersect_key($edges, array_flip($hostNodes)), $corpusByHost[$host]);
        }
    }

    /**
     * @param list<string>                $nodes
     * @param array<string, list<string>> $edges
     * @param CorpusState                 $corpus
     */
    public function write(string $host, array $nodes, array $edges, array $corpus): void
    {
        $this->filesystem->dumpFile($this->file($host), json_encode([
            'corpus' => $corpus,
            'nodes' => $nodes,
            'edges' => $edges,
        ], \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{generatedAt: int, stale: bool, nodes: list<string>, edges: array<string, list<string>>}|null
     *                                                                                                            null when the host was never scanned
     */
    public function read(string $host): ?array
    {
        $file = $this->file($host);
        if (! $this->filesystem->exists($file)) {
            return null;
        }

        /** @var array{corpus?: CorpusState, nodes: list<string>, edges: array<string, list<string>>} $data */
        $data = json_decode($this->filesystem->readFile($file), true);

        return [
            'generatedAt' => (int) filemtime($file),
            // A snapshot from before this field existed describes a corpus we cannot
            // name, which is exactly what stale means.
            'stale' => ($data['corpus'] ?? null) !== $this->pageRepo->getPublishedCorpusState($host),
            'nodes' => $data['nodes'],
            'edges' => $data['edges'],
        ];
    }

    private function file(string $host): string
    {
        return $this->varDir.'/page-scan-graph--'.$host;
    }
}
