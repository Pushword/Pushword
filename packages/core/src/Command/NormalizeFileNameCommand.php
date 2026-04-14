<?php

namespace Pushword\Core\Command;

use Doctrine\Persistence\ManagerRegistry;
use Pushword\Core\Entity\Media;
use Pushword\Core\Image\ImageCacheManager;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Utils\MediaFileName;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(name: 'pw:media:normalize-filenames', description: 'Normalize media filenames to URL-safe slugs')]
final readonly class NormalizeFileNameCommand
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private ManagerRegistry $managerRegistry,
        private ImageCacheManager $imageCacheManager,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Option(description: 'Preview changes without applying them', name: 'dry-run')]
        bool $dryRun = false,
        #[Option(description: 'Delete DB entries when file is missing from disk', name: 'delete-missing')]
        bool $deleteMissing = false,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $medias = $this->mediaRepository->findAll();
        $toNormalize = [];

        foreach ($medias as $media) {
            $currentFileName = $media->getFileName();
            $normalizedFileName = MediaFileName::normalizeFromString($currentFileName);

            if ($currentFileName !== $normalizedFileName) {
                $toNormalize[] = ['id' => $media->id, 'old' => $currentFileName, 'new' => $normalizedFileName];
            }
        }

        if ([] === $toNormalize) {
            $io->success('All media filenames are already normalized.');

            return Command::SUCCESS;
        }

        $io->info(\sprintf('%d media(s) need filename normalization.', \count($toNormalize)));

        if ($dryRun) {
            foreach ($toNormalize as $item) {
                $io->writeln(\sprintf('  %s → %s', $item['old'], $item['new']));
            }

            return Command::SUCCESS;
        }

        $progressBar = new ProgressBar($output, \count($toNormalize));
        $progressBar->setMessage('');
        $progressBar->setFormat("%current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% \r\n %message%");
        $progressBar->start();

        $errors = [];
        $deleted = [];

        foreach ($toNormalize as $item) {
            $oldFileName = $item['old'];
            $newFileName = $item['new'];
            $progressBar->setMessage($oldFileName.' → '.$newFileName);

            try {
                $em = $this->managerRegistry->getManager();
                $media = $em->find(Media::class, $item['id']);

                if (! $media instanceof Media) {
                    $errors[] = $oldFileName.': media entity not found';
                    $progressBar->advance();

                    continue;
                }

                $media->setFileName($newFileName);
                $em->flush();

                if (! $media->isImage()) {
                    $this->imageCacheManager->ensurePublicSymlink($media);
                }
            } catch (Throwable $e) {
                $message = $e->getMessage();

                try {
                    $this->managerRegistry->resetManager();
                } catch (Throwable) {
                }

                if ($deleteMissing && str_contains($message, 'file not found on disk')) {
                    try {
                        $em = $this->managerRegistry->getManager();
                        $media = $em->find(Media::class, $item['id']);

                        if ($media instanceof Media) {
                            $em->remove($media);
                            $em->flush();
                            $deleted[] = $oldFileName;
                            $progressBar->advance();

                            continue;
                        }
                    } catch (Throwable) {
                        try {
                            $this->managerRegistry->resetManager();
                        } catch (Throwable) {
                        }
                    }
                }

                $errors[] = $oldFileName.': '.$message;
            }

            $progressBar->advance();
        }

        $progressBar->setMessage('Done');
        $progressBar->finish();

        $output->writeln('');

        if ([] !== $deleted) {
            $io->note(\sprintf('Deleted %d media entry(ies) with missing files:', \count($deleted)));
            $io->listing($deleted);
        }

        if ([] !== $errors) {
            $io->warning('Some files failed to normalize:');
            $io->listing($errors);
        }

        $io->success(\sprintf('Normalized %d media filename(s).', \count($toNormalize) - \count($errors) - \count($deleted)));

        return Command::SUCCESS;
    }
}
