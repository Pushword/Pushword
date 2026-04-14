<?php

namespace Pushword\Core\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Pushword\Core\Entity\Media;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Service\MediaStorageAdapter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(name: 'pw:media:clean-missing', description: 'Remove media entries whose file no longer exists on disk')]
final readonly class CleanMissingMediaCommand
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private ManagerRegistry $managerRegistry,
        private MediaStorageAdapter $mediaStorageAdapter,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Option(description: 'Preview missing media without removing them', name: 'dry-run')]
        bool $dryRun = false,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $allMedia = $this->mediaRepository->findAll();

        $missing = array_filter($allMedia, fn (Media $media): bool => ! $this->mediaStorageAdapter->fileExists($media->getFileName()));

        if ([] === $missing) {
            $io->success('No missing media found.');

            return Command::SUCCESS;
        }

        $io->info(\sprintf('%d media entry(ies) with missing file(s).', \count($missing)));

        if ($dryRun) {
            foreach ($missing as $media) {
                $io->writeln(\sprintf('  #%d %s', $media->id, $media->getFileName()));
            }

            return Command::SUCCESS;
        }

        $progressBar = new ProgressBar($output, \count($missing));
        $progressBar->setMessage('');
        $progressBar->setFormat("%current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% \r\n %message%");
        $progressBar->start();

        $errors = [];
        $removed = 0;

        foreach ($missing as $media) {
            $mediaId = $media->id;
            $fileName = $media->getFileName();
            $progressBar->setMessage(\sprintf('Removing #%d %s', $mediaId, $fileName));

            try {
                /** @var EntityManagerInterface $em */
                $em = $this->managerRegistry->getManager();
                $media = $em->find(Media::class, $mediaId);

                if (! $media instanceof Media) {
                    $errors[] = \sprintf('#%d: media entity not found', $mediaId);
                    $progressBar->advance();

                    continue;
                }

                $em->remove($media);
                $em->flush();

                ++$removed;
            } catch (Throwable $e) {
                $errors[] = \sprintf('#%d %s: %s', $mediaId, $fileName, $e->getMessage());

                try {
                    $this->managerRegistry->resetManager();
                } catch (Throwable) {
                }
            }

            $progressBar->advance();
        }

        $progressBar->setMessage('Done');
        $progressBar->finish();

        $output->writeln('');

        if ([] !== $errors) {
            $io->warning('Some media entries failed to remove:');
            $io->listing($errors);
        }

        $io->success(\sprintf('Removed %d missing media entry(ies).', $removed));

        $output->writeln(\sprintf('<comment>:: peak memory: %.1f MB</comment>', memory_get_peak_usage(true) / 1024 / 1024));

        return Command::SUCCESS;
    }
}
