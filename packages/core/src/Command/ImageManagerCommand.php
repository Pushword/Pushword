<?php

namespace Pushword\Core\Command;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Entity\Media;
use Pushword\Core\Image\ImageCacheManager;
use Pushword\Core\Image\ImageReader;
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
        private ImageReader $imageReader,
        private LockFactory $lockFactory,
        private string $projectDir,
    ) {
    }

    public function __invoke(
        #[Argument(description: 'Image name(s), comma-separated (eg: a.jpg,b.png).', name: 'media')]
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
        $startTime = microtime(true);

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
                ? $this->resolveMediaNames($mediaName)
                : $this->mediaRepository->findAll();

            $workers = $parallel > 0 ? $parallel : $this->detectCpuCount();

            if (null === $mediaName && $workers > 1) {
                $io->info(\sprintf('Image driver: %s', $this->imageReader->getResolvedDriver()));

                return $this->executeParallel($medias, $workers, $force, $io, $output, $startTime);
            }

            if (null === $mediaName) {
                $io->info(\sprintf('Image driver: %s', $this->imageReader->getResolvedDriver()));
            }

            return $this->executeSequential($medias, $force, $io, $output, $startTime);
        } finally {
            $lock?->release();
        }
    }

    /**
     * @return Media[]
     */
    private function resolveMediaNames(string $mediaName): array
    {
        $names = str_contains($mediaName, ',')
            ? array_map(trim(...), explode(',', $mediaName))
            : [$mediaName];

        $medias = [];
        foreach ($names as $name) {
            $found = $this->mediaRepository->findBy(['fileName' => $name]);
            $medias = [...$medias, ...$found];
        }

        return $medias;
    }

    /**
     * @param Media[] $medias
     */
    private function executeSequential(array $medias, bool $force, SymfonyStyle $io, OutputInterface $output, float $startTime): int
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

        $this->reportSummary($io, \count($medias) - $skipped - \count($errors), $skipped, \count($errors), $startTime);
        $this->reportErrors($errors, $io);

        return 0;
    }

    /**
     * @param Media[] $medias
     */
    private function executeParallel(array $medias, int $workers, bool $force, SymfonyStyle $io, OutputInterface $output, float $startTime): int
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

        // Pre-filter: skip images whose cache is already fresh
        $staleMedias = $imageMedias;
        $preSkipped = 0;
        if (! $force) {
            $staleMedias = [];
            foreach ($imageMedias as $media) {
                if ($this->imageCacheManager->isAllCacheFresh($media)) {
                    ++$preSkipped;
                } else {
                    $staleMedias[] = $media;
                }
            }

            if ($preSkipped > 0) {
                $io->info(\sprintf('%d image(s) already cached, %d to process', $preSkipped, \count($staleMedias)));
            }
        }

        $staleCount = \count($staleMedias);
        if (0 === $staleCount) {
            $this->reportSummary($io, 0, $preSkipped, 0, $startTime);

            return 0;
        }

        // Batch images per worker to amortize kernel boot cost
        $chunks = array_chunk($staleMedias, max(1, (int) ceil($staleCount / $workers)));
        $io->info(\sprintf('Processing %d image(s) in %d batch(es) with %d worker(s)', $staleCount, \count($chunks), $workers));

        $progressBar = $this->createProgressBar($output, $staleCount);

        /** @var array<int, array{process: Process, count: int}> $running */
        $running = [];
        $errors = [];
        $chunkIndex = 0;

        while ($chunkIndex < \count($chunks) || [] !== $running) {
            while ($chunkIndex < \count($chunks) && \count($running) < $workers) {
                $chunk = $chunks[$chunkIndex];
                ++$chunkIndex;
                $fileNames = implode(',', array_map(static fn (Media $m): string => $m->getFileName(), $chunk));

                $cmd = ['php', 'bin/console', 'pw:image:cache', $fileNames, '--no-lock'];
                if ($force) {
                    $cmd[] = '--force';
                }

                $process = new Process($cmd, $this->projectDir);
                $process->setTimeout(300);
                $process->start();
                $running[] = ['process' => $process, 'count' => \count($chunk)];
                $progressBar->setMessage(\sprintf('batch %d/%d (%d images)', $chunkIndex, \count($chunks), \count($chunk)));
            }

            foreach ($running as $key => ['process' => $process, 'count' => $count]) {
                if (! $process->isRunning()) {
                    if (! $process->isSuccessful()) {
                        $stderr = trim($process->getErrorOutput());
                        $errors[] = 'batch: '.('' !== $stderr ? $stderr : 'exit code '.$process->getExitCode());
                    }

                    unset($running[$key]);
                    $progressBar->advance($count);
                }
            }

            if ([] !== $running) {
                usleep(50_000);
            }
        }

        $progressBar->finish();

        $this->reportSummary($io, $staleCount - \count($errors), $preSkipped, \count($errors), $startTime);
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

    private function reportSummary(SymfonyStyle $io, int $processed, int $skipped, int $errored, float $startTime): void
    {
        $elapsed = microtime(true) - $startTime;
        $io->writeln(\sprintf(
            '<comment>:: %d processed, %d skipped, %d errored | %.1fs | peak memory: %.1f MB</comment>',
            $processed,
            $skipped,
            $errored,
            $elapsed,
            memory_get_peak_usage(true) / 1024 / 1024,
        ));
    }

    private function detectCpuCount(): int
    {
        return max(1, (int) (shell_exec('nproc') ?: 4));
    }
}
