<?php

namespace Pushword\PageScanner\Command;

use DateTimeInterface;
use Pushword\Core\Command\AgentOutputTrait;
use Pushword\PageScanner\Service\LinkGraphBuilder;
use Pushword\PageScanner\Service\LinkGraphStorage;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Reports the internal link graph `pw:page-scan` collected while rendering.
 *
 * It never renders anything itself: it reads the snapshot the scan left behind,
 * and runs the scan synchronously when there is none (the API mirror polls a
 * background scan instead — a CI gate cannot). The snapshot's age is always
 * reported, because a page's inbound count changes when *other* pages are
 * edited, so a stale graph misleads without anything on the page having moved.
 *
 * @phpstan-import-type LinkGraph from LinkGraphBuilder
 */
#[AsCommand(
    name: 'pw:link:graph',
    description: 'Report internal link graph: inbound/outbound links, depth from the homepage, orphans.'
)]
final readonly class LinkGraphCommand
{
    use AgentOutputTrait;

    public function __construct(
        private LinkGraphStorage $storage,
        private LinkGraphBuilder $builder,
        private PageScannerCommand $pageScannerCommand,
    ) {
    }

    public function __invoke(
        OutputInterface $output,
        #[Argument(name: 'host')]
        ?string $host = null,
        #[Option(description: 'Restrict the report to one page (slug), with its inbound sources', name: 'page')]
        ?string $page = null,
        #[Option(description: 'List only orphans, and exit non-zero if any remain', name: 'orphans')]
        bool $orphans = false,
        #[Option(description: 'Skip external link checks when a scan has to run first (the graph never uses them)', name: 'skip-external')]
        bool $skipExternal = false,
        #[Option(description: 'Output format: auto (compact JSON when an AI agent is detected), agent (force JSON), or text', name: 'format')]
        string $format = 'auto',
    ): int {
        $agentMode = $this->isAgentFormat($format);

        $snapshot = $this->storage->read($host);
        if (null === $snapshot) {
            if (Command::SUCCESS !== ($exitCode = $this->runScan($output, $host, $agentMode, $skipExternal))) {
                return $exitCode;
            }

            $snapshot = $this->storage->read($host);
        }

        if (null === $snapshot) {
            return $this->fail($output, $agentMode, 'No link graph available: pw:page-scan did not complete.');
        }

        $graph = $this->builder->build($snapshot['nodes'], $snapshot['edges']);
        $generatedAt = date(DateTimeInterface::ATOM, $snapshot['generatedAt']);

        if (null !== $page) {
            return $this->reportPage($output, $agentMode, $graph, $generatedAt, $host, $page);
        }

        return $orphans
            ? $this->reportOrphans($output, $agentMode, $graph, $generatedAt, $host)
            : $this->reportGraph($output, $agentMode, $graph, $generatedAt, $host);
    }

    /**
     * No snapshot yet: render the corpus through the real scan, in-process, so a
     * CI gate blocks on it. Its own PID lock may decline (another scan running),
     * leaving no snapshot — the caller reports that rather than looping.
     *
     * The full scan runs by default: it also refreshes the error cache the admin
     * reads, and skipping external checks silently would leave that cache
     * claiming a clean bill of health it never verified.
     */
    private function runScan(OutputInterface $output, ?string $host, bool $agentMode, bool $skipExternal): int
    {
        if (! $agentMode) {
            $output->writeln('<comment>No link graph found, running pw:page-scan first...</comment>');
        }

        return ($this->pageScannerCommand)(
            $output,
            $host,
            skipExternal: $skipExternal,
            format: $agentMode ? 'agent' : 'text',
        );
    }

    /**
     * @param LinkGraph $graph
     */
    private function reportGraph(OutputInterface $output, bool $agentMode, array $graph, string $generatedAt, ?string $host): int
    {
        if ($agentMode) {
            $this->agentJson($output, 'done', [
                'generatedAt' => $generatedAt,
                'host' => $host,
                'pageCount' => $graph['pageCount'],
                'edgeCount' => $graph['edgeCount'],
                'orphanCount' => $graph['orphanCount'],
                'hostsWithoutHomepage' => $graph['hostsWithoutHomepage'],
                'pages' => $graph['pages'],
            ]);

            return Command::SUCCESS;
        }

        $output->writeln(\sprintf(
            '<comment>%d pages, %d internal links, %d orphans</comment> (graph generated %s)',
            $graph['pageCount'],
            $graph['edgeCount'],
            $graph['orphanCount'],
            $generatedAt,
        ));
        $this->warnHostsWithoutHomepage($output, $graph['hostsWithoutHomepage']);
        $output->writeln('');

        foreach ($graph['pages'] as $page) {
            $output->writeln(\sprintf(
                '%s  <info>in:%d</info> out:%d depth:%s',
                $page['host'].'/'.$page['slug'],
                $page['inboundCount'],
                $page['outboundCount'],
                $page['depth'] ?? '-',
            ));
        }

        return Command::SUCCESS;
    }

    /**
     * @param LinkGraph $graph
     */
    private function reportOrphans(OutputInterface $output, bool $agentMode, array $graph, string $generatedAt, ?string $host): int
    {
        $orphans = array_values(array_filter($graph['pages'], LinkGraphBuilder::isOrphan(...)));
        $passed = [] === $orphans;

        if ($agentMode) {
            $this->agentJson($output, $passed ? 'passed' : 'failed', [
                'generatedAt' => $generatedAt,
                'host' => $host,
                'pageCount' => $graph['pageCount'],
                'orphanCount' => \count($orphans),
                'hostsWithoutHomepage' => $graph['hostsWithoutHomepage'],
                'orphans' => $orphans,
            ]);

            return $passed ? Command::SUCCESS : Command::FAILURE;
        }

        if ($passed) {
            $output->writeln(\sprintf('<info>No orphan among %d pages.</info> (graph generated %s)', $graph['pageCount'], $generatedAt));

            return Command::SUCCESS;
        }

        $output->writeln(\sprintf(
            '<error>%d orphan(s) among %d pages</error> (graph generated %s)',
            \count($orphans),
            $graph['pageCount'],
            $generatedAt,
        ));
        foreach ($orphans as $page) {
            $output->writeln(\sprintf('  %s  <info>in:%d</info>', $page['host'].'/'.$page['slug'], $page['inboundCount']));
        }

        return Command::FAILURE;
    }

    /**
     * @param LinkGraph $graph
     */
    private function reportPage(OutputInterface $output, bool $agentMode, array $graph, string $generatedAt, ?string $host, string $slug): int
    {
        $slug = trim($slug, '/') ?: 'homepage';

        $matches = array_values(array_filter(
            $graph['pages'],
            static fn (array $page): bool => $page['slug'] === $slug,
        ));

        if ([] === $matches) {
            return $this->fail($output, $agentMode, \sprintf('Page "%s" is not in the graph (never scanned, or unpublished).', $slug));
        }

        if ($agentMode) {
            $this->agentJson($output, 'done', [
                'generatedAt' => $generatedAt,
                'host' => $host,
                'pages' => $matches,
            ]);

            return Command::SUCCESS;
        }

        foreach ($matches as $page) {
            $output->writeln(\sprintf(
                '<comment>%s</comment>  in:%d out:%d depth:%s (graph generated %s)',
                $page['host'].'/'.$page['slug'],
                $page['inboundCount'],
                $page['outboundCount'],
                $page['depth'] ?? '-',
                $generatedAt,
            ));
            foreach ($page['inbound'] as $source) {
                $output->writeln('  ← '.$source);
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @param list<string> $hosts
     */
    private function warnHostsWithoutHomepage(OutputInterface $output, array $hosts): void
    {
        if ([] === $hosts) {
            return;
        }

        $output->writeln(\sprintf(
            '<comment>No homepage scanned on %s: depth is unknown there, not infinite.</comment>',
            implode(', ', $hosts),
        ));
    }

    /**
     * Every agent document carries the same envelope; only the payload differs.
     *
     * @param array<string, mixed> $payload
     */
    private function agentJson(OutputInterface $output, string $result, array $payload): void
    {
        $this->writeAgentJson($output, ['tool' => 'pw:link:graph', 'result' => $result, ...$payload]);
    }

    private function fail(OutputInterface $output, bool $agentMode, string $message): int
    {
        if ($agentMode) {
            $this->agentJson($output, 'failed', ['message' => $message]);
        } else {
            $output->writeln('<error>'.$message.'</error>');
        }

        return Command::FAILURE;
    }
}
