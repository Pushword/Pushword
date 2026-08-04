<?php

namespace Pushword\Core\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Pushword\Core\Entity\Media;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Repository\MediaUsageRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * Removes the media no page references.
 *
 * "No page references it" is the whole promise — nothing scans Twig templates, so a
 * navbar logo or an OG fallback looks exactly like a forgotten upload here. Hence a
 * dry run by default and `--force` to mean it, rather than the other way round.
 */
#[AsCommand(
    name: 'pw:media:clean-unused',
    description: 'Remove the media no page references (dry-run unless --force; templates are not scanned)',
)]
final readonly class CleanUnusedMediaCommand
{
    use AgentOutputTrait;

    public function __construct(
        private MediaRepository $mediaRepository,
        private MediaUsageRepository $mediaUsageRepository,
        private ManagerRegistry $managerRegistry,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Option(description: 'Actually delete the media entries and their files')]
        bool $force = false,
        #[Option(description: 'Output format: auto|agent|text')]
        string $format = 'auto',
    ): int {
        $agentMode = $this->isAgentFormat($format);
        $io = new SymfonyStyle($input, $output);

        // An empty usage table cannot be told from a site whose every media is
        // orphaned, and guessing wrong here deletes the whole media library.
        if (! $this->mediaUsageRepository->hasAny()) {
            if ($agentMode) {
                $this->writeAgentJson($output, [
                    'tool' => 'pw:media:clean-unused',
                    'result' => 'failed',
                    'error' => 'media usage table is empty — run pw:media:usage:rebuild first',
                ]);

                return Command::FAILURE;
            }

            $io->error('The media usage table is empty. Run `pw:media:usage:rebuild` first — until it has been built, every media looks unreferenced.');

            return Command::FAILURE;
        }

        $unused = $this->mediaRepository->findNotReferencedByAPage();

        if ([] === $unused) {
            if ($agentMode) {
                $this->writeAgentJson($output, [
                    'tool' => 'pw:media:clean-unused',
                    'result' => 'done',
                    'dry_run' => ! $force,
                    'found' => 0,
                    'removed' => 0,
                ]);

                return Command::SUCCESS;
            }

            $io->success('Every media is referenced by at least one page.');

            return Command::SUCCESS;
        }

        if (! $force) {
            return $this->report($output, $io, $unused, $agentMode);
        }

        return $this->remove($output, $io, $unused, $agentMode);
    }

    /** @param Media[] $unused */
    private function report(OutputInterface $output, SymfonyStyle $io, array $unused, bool $agentMode): int
    {
        if ($agentMode) {
            $this->writeAgentJson($output, [
                'tool' => 'pw:media:clean-unused',
                'result' => 'done',
                'dry_run' => true,
                'found' => \count($unused),
                'removed' => 0,
                'media' => array_map(static fn (Media $media): array => [
                    'id' => $media->id,
                    'file_name' => $media->getFileName(),
                ], array_values($unused)),
            ]);

            return Command::SUCCESS;
        }

        $io->info(\sprintf('%d media referenced by no page.', \count($unused)));

        foreach ($unused as $media) {
            $io->writeln(\sprintf('  #%d %s', $media->id, $media->getFileName()));
        }

        $io->warning('A media used only from a Twig template — a navbar logo, an OG fallback — is listed here too: nothing scans templates. Read the list before running with --force.');

        return Command::SUCCESS;
    }

    /** @param Media[] $unused */
    private function remove(OutputInterface $output, SymfonyStyle $io, array $unused, bool $agentMode): int
    {
        $progressBar = null;
        if (! $agentMode) {
            $progressBar = new ProgressBar($output, \count($unused));
            $progressBar->setMessage('');
            $progressBar->setFormat("%current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% \r\n %message%");
            $progressBar->start();
        }

        $errors = [];
        $removed = 0;

        foreach ($unused as $media) {
            $mediaId = (int) $media->id;
            $fileName = $media->getFileName();
            $progressBar?->setMessage(\sprintf('Removing #%d %s', $mediaId, $fileName));

            try {
                /** @var EntityManagerInterface $em */
                $em = $this->managerRegistry->getManager();
                $found = $em->find(Media::class, $mediaId);

                if (! $found instanceof Media) {
                    $errors[] = \sprintf('#%d: media entity not found', $mediaId);
                    $progressBar?->advance();

                    continue;
                }

                $em->remove($found);
                $em->flush();

                ++$removed;
            } catch (Throwable $throwable) {
                $errors[] = \sprintf('#%d %s: %s', $mediaId, $fileName, $throwable->getMessage());

                try {
                    $this->managerRegistry->resetManager();
                } catch (Throwable) {
                }
            }

            $progressBar?->advance();
        }

        $progressBar?->setMessage('Done');
        $progressBar?->finish();

        if ($agentMode) {
            $this->writeAgentJson($output, [
                'tool' => 'pw:media:clean-unused',
                'result' => [] === $errors ? 'done' : 'partial',
                'dry_run' => false,
                'found' => \count($unused),
                'removed' => $removed,
                'errors' => $errors,
            ]);

            return Command::SUCCESS;
        }

        $output->writeln('');

        if ([] !== $errors) {
            $io->warning('Some media failed to be removed:');
            $io->listing($errors);
        }

        $io->success(\sprintf('Removed %d media referenced by no page.', $removed));

        $output->writeln(\sprintf('<comment>:: peak memory: %.1f MB</comment>', memory_get_peak_usage(true) / 1024 / 1024));

        return Command::SUCCESS;
    }
}
