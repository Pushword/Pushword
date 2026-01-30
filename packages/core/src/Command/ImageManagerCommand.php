<?php

namespace Pushword\Core\Command;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Image\ImageCacheManager;
use Pushword\Core\Image\ThumbnailGenerator;
use Pushword\Core\Repository\MediaRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Lock\LockFactory;
use Throwable;

#[AsCommand(name: 'pw:image:cache', description: 'Generate all images cache')]
final readonly class ImageManagerCommand
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private EntityManagerInterface $entityManager,
        private ThumbnailGenerator $thumbnailGenerator,
        private ImageCacheManager $imageCacheManager,
        private LockFactory $lockFactory,
    ) {
    }

    public function __invoke(
        #[Argument(description: 'Image name (eg: filename.jpg).', name: 'media')]
        ?string $mediaName,
        InputInterface $input,
        OutputInterface $output,
        #[Option(description: 'Force regeneration even if cache is fresh', name: 'force', shortcut: 'f')]
        bool $force = false,
    ): int {
        $io = new SymfonyStyle($input, $output);

        $lock = $this->lockFactory->createLock('pw:image:cache');
        if (! $lock->acquire(blocking: false)) {
            $io->info('Another instance of pw:image:cache is already running. Skipping.');

            return 0;
        }

        try {
            $medias = null !== $mediaName ? $this->mediaRepository->findBy(['fileName' => $mediaName]) : $this->mediaRepository->findAll();

            $progressBar = new ProgressBar($output, \count($medias));
            $progressBar->setMessage('');
            $progressBar->setFormat("%current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% \r\n %message%");
            $progressBar->start();

            $errors = [];
            $skipped = 0;

            foreach ($medias as $media) {
                if ($media->isImage()) {
                    $progressBar->setMessage($media->getPath());

                    try {
                        $generated = $this->thumbnailGenerator->generateCache($media, $force);
                        if (! $generated) {
                            ++$skipped;
                        }
                    } catch (Throwable $exception) {
                        $errors[] = $media->getFileName().': '.$exception->getMessage();
                    }
                } else {
                    // For non-images, create symlink from public/media to media
                    $this->imageCacheManager->ensurePublicSymlink($media);
                }

                $progressBar->advance();
            }

            $this->entityManager->flush();
            $progressBar->finish();

            if ($skipped > 0) {
                $io->info(\sprintf('%d image(s) skipped (cache already fresh)', $skipped));
            }

            if ([] !== $errors) {
                $io->warning('Some images failed to process:');
                $io->listing($errors);
            }

            return 0;
        } finally {
            $lock->release();
        }
    }
}
