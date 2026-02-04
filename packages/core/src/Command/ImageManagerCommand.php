<?php

namespace Pushword\Core\Command;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Entity\Media;
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
use Symfony\Component\Process\Process;
use Throwable;

#[AsCommand(name: 'pw:image:cache', description: 'Generate all images cache')]
final readonly class ImageManagerCommand
{
    private const string PROGRESS_FORMAT = "%current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% \r\n %message%";

    public function __construct(
        private MediaRepository $mediaRepository,
        private EntityManagerInterface $entityManager,
        private ThumbnailGenerator $thumbnailGenerator,
        private ImageCacheManager $imageCacheManager,
        private LockFactory $lockFactory,
        private string $projectDir,
    ) {
    }

    public function __invoke(
        #[Argument(description: 'Image name (eg: filename.jpg).', name: 'media')]
        ?string $mediaName,
        InputInterface $input,
        OutputInterface $output,
        #[Option(description: 'Force regeneration even if cache is fresh', name: 'force', shortcut: 'f')]
        bool $force = false,
        #[Option(description: 'Number of parallel workers (0 = auto, 1 = sequential)', name: 'parallel', shortcut: 'p')]
        int $parallel = 0,
        #[Option(description: 'Skip lock (internal use by parallel workers)', name: 'no-lock')]
        bool $noLock = false,
    ): int {
        $io = new SymfonyStyle($input, $output);

        $lock = null;
        if (! $noLock) {
            $lock = $this->lockFactory->createLock('pw:image:cache');
            if (! $lock->acquire(blocking: false)) {
                $io->info('Another instance of pw:image:cache is already running. Skipping.');

                return 0;
            }
        }

        try {
            $medias = null !== $mediaName
                ? $this->mediaRepository->findBy(['fileName' => $mediaName])
                : $this->mediaRepository->findAll();

            $workers = $parallel > 0 ? $parallel : $this->detectCpuCount();

            if (null === $mediaName && $workers > 1) {
                return $this->executeParallel($medias, $workers, $force, $io, $output);
            }

            return $this->executeSequential($medias, $force, $io, $output);
        } finally {
            $lock?->release();
        }
    }

    /**
     * @param Media[] $medias
     */
    private function executeSequential(array $medias, bool $force, SymfonyStyle $io, OutputInterface $output): int
    {
        $progressBar = $this->createProgressBar($output, \count($medias));

        $errors = [];
        $skipped = 0;
        $processed = 0;

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
                $this->imageCacheManager->ensurePublicSymlink($media);
            }

            $progressBar->advance();

            if (0 === ++$processed % 50) {
                $this->entityManager->flush();
            }
        }

        $this->entityManager->flush();
        $progressBar->finish();

        if ($skipped > 0) {
            $io->info(\sprintf('%d image(s) skipped (cache already fresh)', $skipped));
        }

        $this->reportErrors($errors, $io);

        return 0;
    }

    /**
     * @param Media[] $medias
     */
    private function executeParallel(array $medias, int $workers, bool $force, SymfonyStyle $io, OutputInterface $output): int
    {
        $imageMedias = [];
        foreach ($medias as $media) {
            if ($media->isImage()) {
                $imageMedias[] = $media;
            } else {
                $this->imageCacheManager->ensurePublicSymlink($media);
            }
        }

        $total = \count($imageMedias);
        if (0 === $total) {
            $io->info('No images to process.');

            return 0;
        }

        $io->info(\sprintf('Processing %d image(s) with %d parallel worker(s)', $total, $workers));

        $progressBar = $this->createProgressBar($output, $total);

        /** @var array<string, Process> $running */
        $running = [];
        $errors = [];
        $index = 0;

        while ($index < $total || [] !== $running) {
            while ($index < $total && \count($running) < $workers) {
                $media = $imageMedias[$index];
                ++$index;
                $fileName = $media->getFileName();

                $cmd = ['php', 'bin/console', 'pw:image:cache', $fileName, '--no-lock'];
                if ($force) {
                    $cmd[] = '--force';
                }

                $process = new Process($cmd, $this->projectDir);
                $process->setTimeout(300);
                $process->start();
                $running[$fileName] = $process;
                $progressBar->setMessage($fileName);
            }

            foreach ($running as $fileName => $process) {
                if (! $process->isRunning()) {
                    if (! $process->isSuccessful()) {
                        $stderr = trim($process->getErrorOutput());
                        $errors[] = $fileName.': '.('' !== $stderr ? $stderr : 'exit code '.$process->getExitCode());
                    }

                    unset($running[$fileName]);
                    $progressBar->advance();
                }
            }

            if ([] !== $running) {
                usleep(50_000);
            }
        }

        $progressBar->finish();
        $this->reportErrors($errors, $io);

        return 0;
    }

    private function createProgressBar(OutputInterface $output, int $max): ProgressBar
    {
        $progressBar = new ProgressBar($output, $max);
        $progressBar->setMessage('');
        $progressBar->setFormat(self::PROGRESS_FORMAT);
        $progressBar->start();

        return $progressBar;
    }

    /** @param string[] $errors */
    private function reportErrors(array $errors, SymfonyStyle $io): void
    {
        if ([] !== $errors) {
            $io->warning('Some images failed to process:');
            $io->listing($errors);
        }
    }

    private function detectCpuCount(): int
    {
        return max(1, (int) (shell_exec('nproc') ?: 4));
    }
}
