<?php

namespace Pushword\LinkImprover\Command;

use Pushword\Core\Command\AgentOutputTrait;
use Pushword\Core\Content\ContentPipelineFactory;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Site\SiteRegistry;
use Pushword\LinkImprover\AddedLinksRegistry;
use Pushword\LinkImprover\InternalLinkSources;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * The audit surface of the improver: renders every published page and lists
 * what was auto-linked, where, with which anchor. `--simulate` renders as if
 * `link_improver: true` were set, to preview a site before opting in.
 */
#[AsCommand(name: 'pw:link-improver', description: 'Render each published page and report the internal links the improver inserts')]
final class LinkImproverReportCommand
{
    use AgentOutputTrait;

    private bool $agentMode = false;

    public function __construct(
        private readonly SiteRegistry $apps,
        private readonly PageRepository $pageRepository,
        private readonly ContentPipelineFactory $pipelineFactory,
        private readonly InternalLinkSources $sources,
        private readonly AddedLinksRegistry $addedLinks,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Option(description: 'Only report this host', name: 'host')]
        string $host = '',
        #[Option(description: 'Render as if link_improver were enabled — preview before opting in', name: 'simulate')]
        bool $simulate = false,
        #[Option(description: 'Output format: auto (compact JSON when an AI agent is detected), agent (force JSON), or text', name: 'format')]
        string $format = 'auto',
    ): int {
        $this->agentMode = $this->isAgentFormat($format);
        $io = new SymfonyStyle($input, $output);

        // Report what rendering produces NOW: drop the pipelines (and their
        // cached property values), sources and records an earlier render in
        // this process may have left — flushing a page in cache mode renders it.
        $this->pipelineFactory->reset();
        $this->sources->reset();
        $this->addedLinks->reset();

        $pagesRendered = 0;
        $links = [];
        $errors = [];
        $skippedHosts = [];

        foreach ($this->apps->getAll() as $site) {
            $siteHost = $site->getMainHost();
            if ('' !== $host && ! \in_array($host, $site->hosts, true)) {
                continue;
            }

            if (true !== $site->get('link_improver')) {
                if (! $simulate) {
                    $skippedHosts[] = $siteHost;

                    continue;
                }

                $site->setCustomProperty('link_improver', true); // this process only, nothing is persisted
            }

            foreach ($this->pageRepository->getPublishedPages($siteHost, withRedirection: false) as $page) {
                ++$pagesRendered;

                try {
                    $this->pipelineFactory->get($page)->getMainContent();
                } catch (Throwable $throwable) {
                    $errors[] = ['page' => '/'.$page->slug, 'host' => $siteHost, 'error' => $throwable->getMessage()];

                    continue;
                }

                foreach ($this->addedLinks->forPage($page) as $addedLink) {
                    $links[] = ['host' => $siteHost, 'page' => '/'.$page->slug, ...$addedLink];
                }
            }
        }

        return $this->report($io, $output, $pagesRendered, $links, $errors, $skippedHosts);
    }

    /**
     * @param list<array{host: string, page: string, anchor: string, url: string}> $links
     * @param list<array{page: string, host: string, error: string}>               $errors
     * @param list<string>                                                         $skippedHosts
     */
    private function report(SymfonyStyle $io, OutputInterface $output, int $pagesRendered, array $links, array $errors, array $skippedHosts): int
    {
        $exitCode = [] === $errors ? Command::SUCCESS : Command::FAILURE;

        if ($this->agentMode) {
            $this->writeAgentJson($output, [
                'tool' => 'pw:link-improver',
                'result' => [] === $errors ? 'ok' : 'render_errors',
                'pages_rendered' => $pagesRendered,
                'links_added' => \count($links),
                'links' => $links,
                'errors' => $errors,
                'skipped_hosts' => $skippedHosts,
            ]);

            return $exitCode;
        }

        foreach ($skippedHosts as $skippedHost) {
            $io->note(\sprintf('%s: link_improver is not enabled — skipped (use --simulate to preview).', $skippedHost));
        }

        $byPage = [];
        foreach ($links as $link) {
            $byPage[$link['host'].$link['page']][] = $link;
        }

        foreach ($byPage as $pageKey => $pageLinks) {
            $io->section($pageKey);
            foreach ($pageLinks as $link) {
                $io->writeln(\sprintf('  + "%s" → %s', $link['anchor'], $link['url']));
            }
        }

        foreach ($errors as $error) {
            $io->error(\sprintf('%s%s: %s', $error['host'], $error['page'], $error['error']));
        }

        $io->success(\sprintf('%d page(s) rendered, %d link(s) inserted.', $pagesRendered, \count($links)));

        return $exitCode;
    }
}
