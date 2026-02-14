<?php

namespace Pushword\Core\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Pushword\Core\Entity\Media;
use Pushword\Core\Repository\MediaRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(name: 'pw:media:clean-duplicates', description: 'Merge duplicate media entries sharing the same file content')]
final readonly class CleanDuplicateMediaCommand
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private ManagerRegistry $managerRegistry,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Option(description: 'Preview duplicates without merging them', name: 'dry-run')]
        bool $dryRun = false,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $duplicateGroups = $this->mediaRepository->findDuplicateGroups();

        if ([] === $duplicateGroups) {
            $io->success('No duplicate media found.');

            return Command::SUCCESS;
        }

        $totalDuplicates = array_sum(array_map(static fn (array $group): int => \count($group) - 1, $duplicateGroups));
        $io->info(\sprintf('%d duplicate(s) found in %d group(s).', $totalDuplicates, \count($duplicateGroups)));

        if ($dryRun) {
            foreach ($duplicateGroups as $medias) {
                $canonical = $medias[0];
                $io->writeln(\sprintf('  Keep #%d %s', $canonical->id, $canonical->getFileName()));

                for ($i = 1, $count = \count($medias); $i < $count; ++$i) {
                    $io->writeln(\sprintf('    Remove #%d %s', $medias[$i]->id, $medias[$i]->getFileName()));
                }
            }

            return Command::SUCCESS;
        }

        $progressBar = new ProgressBar($output, $totalDuplicates);
        $progressBar->setMessage('');
        $progressBar->setFormat("%current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% \r\n %message%");
        $progressBar->start();

        $errors = [];
        $merged = 0;

        foreach ($duplicateGroups as $medias) {
            $canonicalId = $medias[0]->id;

            for ($i = 1, $count = \count($medias); $i < $count; ++$i) {
                $duplicateId = $medias[$i]->id;
                $duplicateFileName = $medias[$i]->getFileName();
                $progressBar->setMessage(\sprintf('Merging #%d %s → #%d', $duplicateId, $duplicateFileName, $canonicalId));

                try {
                    /** @var EntityManagerInterface $em */
                    $em = $this->managerRegistry->getManager();
                    $canonical = $em->find(Media::class, $canonicalId);
                    $duplicate = $em->find(Media::class, $duplicateId);

                    if (! $canonical instanceof Media || ! $duplicate instanceof Media) {
                        $errors[] = \sprintf('#%d: media entity not found', $duplicateId);
                        $progressBar->advance();

                        continue;
                    }

                    $canonical->addFileNameToHistory($duplicate->getFileName());
                    foreach ($duplicate->getFileNameHistory() as $historyName) {
                        $canonical->addFileNameToHistory($historyName);
                    }

                    $em->flush();

                    // Transfer page references via SQL before removing the duplicate.
                    // This ensures @PreRemove finds no pages to nullify.
                    $em->getConnection()->executeStatement(
                        'UPDATE page SET main_image_id = :canonical WHERE main_image_id = :duplicate',
                        ['canonical' => $canonicalId, 'duplicate' => $duplicateId],
                    );

                    // Remove duplicate (triggers file + cache cleanup via MediaStorageListener)
                    $em->remove($duplicate);
                    $em->flush();

                    ++$merged;
                } catch (Throwable $e) {
                    $errors[] = \sprintf('#%d %s: %s', $duplicateId, $duplicateFileName, $e->getMessage());

                    try {
                        $this->managerRegistry->resetManager();
                    } catch (Throwable) {
                    }
                }

                $progressBar->advance();
            }
        }

        $progressBar->setMessage('Done');
        $progressBar->finish();

        $output->writeln('');

        if ([] !== $errors) {
            $io->warning('Some duplicates failed to merge:');
            $io->listing($errors);
        }

        $io->success(\sprintf('Merged %d duplicate media(s).', $merged));

        $output->writeln(\sprintf('<comment>:: peak memory: %.1f MB</comment>', memory_get_peak_usage(true) / 1024 / 1024));

        return Command::SUCCESS;
    }
}
