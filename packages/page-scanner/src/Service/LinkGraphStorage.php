<?php

namespace Pushword\PageScanner\Service;

use function Safe\json_decode;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Reads and writes the link-graph snapshot `pw:page-scan` leaves behind, next to
 * the error cache it already writes and with the same per-host scoping.
 *
 * It stores the raw nodes and edges rather than a built report, so the report
 * can change shape without a rescan, and stores the scanned node list rather
 * than re-querying at read time, so a page published after the scan cannot show
 * up as an orphan that was never rendered. Written as JSON (not serialize()
 * like its neighbour) because a graph is worth inspecting by hand.
 */
final readonly class LinkGraphStorage
{
    public function __construct(
        private Filesystem $filesystem,
        private string $varDir,
    ) {
    }

    /**
     * @param list<string>                $nodes
     * @param array<string, list<string>> $edges
     */
    public function write(?string $host, array $nodes, array $edges): void
    {
        $this->filesystem->dumpFile($this->file($host), json_encode([
            'nodes' => $nodes,
            'edges' => $edges,
        ], \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{generatedAt: int, nodes: list<string>, edges: array<string, list<string>>}|null
     *                                                                                               null when the scope was never scanned
     */
    public function read(?string $host): ?array
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

    private function file(?string $host): string
    {
        return $this->varDir.'/page-scan-graph'.(null === $host || '' === $host ? '' : '--'.$host);
    }
}
