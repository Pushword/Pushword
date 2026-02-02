<?php

namespace Pushword\Core\Command;

use Doctrine\ORM\EntityManagerInterface;
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
        private EntityManagerInterface $em,
        private ImageCacheManager $imageCacheManager,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Option(description: 'Preview changes without applying them', name: 'dry-run')]
        bool $dryRun = false,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $medias = $this->mediaRepository->findAll();
        $toNormalize = [];

        foreach ($medias as $media) {
            $currentFileName = $media->getFileName();
            $normalizedFileName = MediaFileName::normalizeFromString($currentFileName);

            if ($currentFileName !== $normalizedFileName) {
                $toNormalize[] = ['media' => $media, 'old' => $currentFileName, 'new' => $normalizedFileName];
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

        foreach ($toNormalize as $item) {
            $media = $item['media'];
            $oldFileName = $item['old'];
            $newFileName = $item['new'];
            $progressBar->setMessage($oldFileName.' → '.$newFileName);

            try {
                $media->setFileName($newFileName);
                $this->em->flush();

                if (! $media->isImage()) {
                    $this->imageCacheManager->ensurePublicSymlink($media);
                }
            } catch (Throwable $e) {
                $errors[] = $oldFileName.': '.$e->getMessage();

                try {
                    $this->em->refresh($media);
                } catch (Throwable) {
                }
            }

            $progressBar->advance();
        }

        $progressBar->setMessage('Done');
        $progressBar->finish();

        $output->writeln('');

        if ([] !== $errors) {
            $io->warning('Some files failed to normalize:');
            $io->listing($errors);
        }

        $io->success(\sprintf('Normalized %d media filename(s).', \count($toNormalize) - \count($errors)));

        return Command::SUCCESS;
    }
}
