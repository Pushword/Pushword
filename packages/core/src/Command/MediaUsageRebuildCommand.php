<?php

namespace Pushword\Core\Command;

use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Repository\MediaUsageRepository;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Service\MediaUsageExtractor;
use Pushword\Core\Service\MediaUsageTracker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Builds `media_usage` from scratch. Needed once, after the upgrade that introduces
 * the table, and again after any bulk write that reached the database without the
 * listener — a restore, an import run against media that did not exist yet.
 *
 * Pages are read in keyset batches of scalar rows, so the peak memory is one batch
 * rather than the corpus, and the rows are written a chunk at a time instead of one
 * statement per edge.
 */
#[AsCommand(
    name: 'pw:media:usage:rebuild',
    description: 'Rebuild from every page the table saying which pages use which media',
)]
final readonly class MediaUsageRebuildCommand
{
    use AgentOutputTrait;

    private const int PAGE_BATCH = 200;

    public function __construct(
        private PageRepository $pageRepository,
        private MediaRepository $mediaRepository,
        private MediaUsageRepository $mediaUsageRepository,
        private MediaUsageExtractor $mediaUsageExtractor,
        private MediaUsageTracker $mediaUsageTracker,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Option(description: 'Output format: auto|agent|text')]
        string $format = 'auto',
    ): int {
        $agentMode = $this->isAgentFormat($format);
        $io = new SymfonyStyle($input, $output);

        $this->mediaUsageRepository->deleteAll();

        $pageCount = 0;
        $usageCount = 0;
        $mediaUsed = [];
        $afterId = 0;

        while ([] !== ($batch = $this->pageRepository->findContentBatchAfter($afterId, self::PAGE_BATCH))) {
            $rows = [];

            foreach ($batch as $page) {
                $afterId = $page['id'];
                ++$pageCount;

                foreach ($this->mediaUsageExtractor->extract($page['mainContent'], $page['customProperties'], $page['mainImageId']) as $usage) {
                    $rows[] = ['mediaId' => $usage['mediaId'], 'pageId' => $page['id'], 'source' => $usage['source']];
                    $mediaUsed[$usage['mediaId']] = true;
                }
            }

            $this->mediaUsageRepository->insert($rows);
            $usageCount += \count($rows);

            if (! $agentMode) {
                $io->writeln(\sprintf('  %d pages scanned, %d usage(s) recorded', $pageCount, $usageCount));
            }
        }

        // Every media, not only the ones just found used: a media that lost its last
        // usage has inherited tags to drop.
        $this->mediaUsageTracker->refreshPageTags($this->mediaRepository->getAllIds());

        $unusedCount = $this->mediaRepository->countNotReferencedByAPage();

        if ($agentMode) {
            $this->writeAgentJson($output, [
                'tool' => 'pw:media:usage:rebuild',
                'result' => 'done',
                'pages' => $pageCount,
                'usages' => $usageCount,
                'media_used' => \count($mediaUsed),
                'media_not_referenced_by_a_page' => $unusedCount,
            ]);

            return Command::SUCCESS;
        }

        $io->success(\sprintf(
            '%d page(s) scanned, %d usage(s) recorded for %d media.',
            $pageCount,
            $usageCount,
            \count($mediaUsed),
        ));

        $io->note(\sprintf(
            '%d media are referenced by no page. That is not the same as unused: nothing scans Twig templates.',
            $unusedCount,
        ));

        $output->writeln(\sprintf('<comment>:: peak memory: %.1f MB</comment>', memory_get_peak_usage(true) / 1024 / 1024));

        return Command::SUCCESS;
    }
}
