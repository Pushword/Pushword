<?php

namespace Pushword\Core\Command;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\BackgroundTask\BackgroundCommand;
use Pushword\Core\Entity\Media;
use Pushword\Core\Image\ImageCacheGenerator;
use Pushword\Core\Image\ImageCacheManager;
use Pushword\Core\Image\ImageReader;
use Pushword\Core\Repository\MediaRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

#[AsCommand(name: 'pw:image:cache', description: 'Generate all images cache')]
final class ImageManagerCommand
{
    use AgentOutputTrait;

    private const string PROGRESS_FORMAT = "%current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% \r\n %message%";

    /**
     * Images per worker batch. Capped, not just divided by the worker count: the
     * batch travels as ONE element of argv, and Linux limits a single argument to
     * MAX_ARG_STRLEN (128 KiB), whatever ARG_MAX allows overall. Sized by division
     * alone it grew with the library — 11k stale medias over 2 workers put ~500 KiB
     * in one argument and posix_spawn() refused before the first image.
     */
    private const int CHUNK_MAX = 200;

    /**
     * Killed after this long *without output*, not this long in total: a worker
     * prints DONE: per image, so silence means stuck, while a wall-clock cap would
     * kill a large batch that is progressing perfectly well. Property, not const,
     * so tests can shrink it through reflection.
     */
    private float $workerIdleTimeout = 300;

    private bool $agentMode = false;

    public function __construct(
        private readonly MediaRepository $mediaRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ImageCacheGenerator $imageCacheGenerator,
        private readonly ImageCacheManager $imageCacheManager,
        private readonly ImageReader $imageReader,
        private readonly LockFactory $lockFactory,
        private readonly string $projectDir,
        #[Autowire(param: 'kernel.environment')]
        private readonly string $environment,
    ) {
    }

    public function __invoke(
        #[Argument(description: 'Image name(s), comma-separated (eg: a.jpg,b.png).', name: 'media')]
        ?string $mediaName,
        InputInterface $input,
        OutputInterface $output,
        #[Option(description: 'Force regeneration even if cache is fresh', name: 'force', shortcut: 'f')]
        bool $force = false,
        #[Option(description: 'Number of parallel workers (0 = auto: a quarter of the CPU cores, 1 = sequential)', name: 'parallel', shortcut: 'p')]
        int $parallel = 0,
        #[Option(description: 'Skip lock (internal use by parallel workers)', name: 'no-lock')]
        bool $noLock = false,
        #[Option(description: 'Output format: auto (compact JSON when an AI agent is detected), agent (force JSON), or text', name: 'format')]
        string $format = 'auto',
    ): int {
        $this->agentMode = $this->isAgentFormat($format);

        $io = new SymfonyStyle($input, $output);
        $startTime = microtime(true);

        $lock = null;
        if (! $noLock) {
            $lockKey = null !== $mediaName ? 'pw:image:cache:'.md5($mediaName) : 'pw:image:cache';
            $lock = $this->lockFactory->createLock($lockKey);
            if (! $lock->acquire(blocking: false)) {
                if ($this->agentMode) {
                    $this->writeAgentJson($output, [
                        'tool' => 'pw:image:cache',
                        'result' => 'running',
                        'message' => 'Another instance of pw:image:cache is already running.',
                    ]);

                    return 0;
                }

                $io->info('Another instance of pw:image:cache is already running. Skipping.');

                return 0;
            }
        }

        try {
            $medias = null !== $mediaName
                ? $this->resolveMediaNames($mediaName)
                : $this->mediaRepository->findAll();

            // Auto (0): use a quarter of the cores (min 1). Each worker already decodes the
            // full master through vips (memory-heavy) AND spawns a background pw:image:optimize
            // whose cwebp runs with -mt (all cores), so one worker per core oversubscribes
            // CPU/RAM and OOM-kills encodes on large images. Override with -p when needed.
            $workers = $parallel > 0 ? $parallel : max(1, intdiv($this->detectCpuCount(), 4));

            if (null === $mediaName && $workers > 1) {
                if (! $this->agentMode) {
                    $io->info(\sprintf('Image driver: %s', $this->imageReader->getResolvedDriver()));
                }

                return $this->executeParallel($medias, $workers, $force, $io, $output, $startTime);
            }

            if (null === $mediaName && ! $this->agentMode) {
                $io->info(\sprintf('Image driver: %s', $this->imageReader->getResolvedDriver()));
            }

            $isWorker = $noLock && null !== $mediaName;

            return $this->executeSequential($medias, $force, $isWorker, $io, $output, $startTime);
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
    private function executeSequential(array $medias, bool $force, bool $isWorker, SymfonyStyle $io, OutputInterface $output, float $startTime): int
    {
        $progressBar = ($isWorker || $this->agentMode) ? null : $this->createProgressBar($output, \count($medias));

        $errors = [];
        $skipped = 0;
        $processed = 0;

        foreach ($medias as $media) {
            if ($media->isImage()) {
                $progressBar?->setMessage($media->getPath());

                try {
                    $generated = $this->imageCacheGenerator->generateCache($media, $force);
                    if (! $generated) {
                        ++$skipped;
                    }
                } catch (Throwable $exception) {
                    $errors[] = $media->getFileName().': '.$exception->getMessage();
                }

                if ($isWorker && ! $this->agentMode) {
                    $output->writeln('DONE:'.$media->getFileName());
                }
            } else {
                $this->imageCacheManager->ensurePublicSymlink($media);
            }

            $progressBar?->advance();

            if (0 === ++$processed % 50) {
                $this->entityManager->flush();
            }
        }

        $this->entityManager->flush();
        $progressBar?->finish();

        if ($this->agentMode) {
            $this->reportAgentSummary($output, \count($medias) - $skipped - \count($errors), $skipped, $errors, $startTime);

            return [] === $errors ? Command::SUCCESS : Command::FAILURE;
        }

        if (! $isWorker) {
            $this->reportSummary($io, \count($medias) - $skipped - \count($errors), $skipped, \count($errors), $startTime);
            $this->reportErrors($errors, $io);
        }

        return [] === $errors ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Images in one worker batch: the total spread over the workers, capped.
     *
     * @return int<1, max>
     */
    private function chunkSize(int $staleCount, int $workers): int
    {
        return min(max(1, (int) ceil($staleCount / $workers)), self::CHUNK_MAX);
    }

    /**
     * --format=text always: the worker inherits the parent environment, so under
     * an agent one auto-detection would silence the DONE: lines the idle timeout
     * reads as liveness, and a healthy long batch would be killed as stuck.
     *
     * @return string[]
     */
    private function workerCommand(string $fileNames, bool $force): array
    {
        $cmd = BackgroundCommand::pinEnvironment(
            ['php', 'bin/console', 'pw:image:cache', $fileNames, '--no-lock', '--format=text'],
            $this->environment,
        );
        if ($force) {
            $cmd[] = '--force';
        }

        return $cmd;
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
            if ($this->agentMode) {
                $this->reportAgentSummary($output, 0, 0, [], $startTime);
            } else {
                $io->info('No images to process.');
            }

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

            if ($preSkipped > 0 && ! $this->agentMode) {
                $io->info(\sprintf('%d image(s) already cached, %d to process', $preSkipped, \count($staleMedias)));
            }
        }

        $staleCount = \count($staleMedias);
        if (0 === $staleCount) {
            if ($this->agentMode) {
                $this->reportAgentSummary($output, 0, $preSkipped, [], $startTime);
            } else {
                $this->reportSummary($io, 0, $preSkipped, 0, $startTime);
            }

            return 0;
        }

        // Batch images per worker to amortize kernel boot cost. More batches than
        // workers is fine — the scheduling loop below feeds them as slots free up.
        $chunks = array_chunk($staleMedias, $this->chunkSize($staleCount, $workers));
        if (! $this->agentMode) {
            $io->info(\sprintf('Processing %d image(s) in %d batch(es) with %d worker(s)', $staleCount, \count($chunks), $workers));
        }

        $progressBar = $this->agentMode ? null : $this->createProgressBar($output, $staleCount);

        /** @var array<int, array{process: Process, count: int, reported: int}> $running */
        $running = [];
        $errors = [];
        $chunkIndex = 0;

        while ($chunkIndex < \count($chunks) || [] !== $running) {
            while ($chunkIndex < \count($chunks) && \count($running) < $workers) {
                $chunk = $chunks[$chunkIndex];
                ++$chunkIndex;
                $fileNames = implode(',', array_map(static fn (Media $m): string => $m->getFileName(), $chunk));

                $process = new Process($this->workerCommand($fileNames, $force), $this->projectDir);
                $process->setTimeout(null);
                $process->setIdleTimeout($this->workerIdleTimeout);
                $process->start();
                $running[] = ['process' => $process, 'count' => \count($chunk), 'reported' => 0];
            }

            foreach ($running as $key => &$entry) {
                $newOutput = $entry['process']->getIncrementalOutput();
                if ('' !== $newOutput) {
                    $lines = substr_count($newOutput, 'DONE:');
                    if ($lines > 0) {
                        $progressBar?->advance($lines);
                        $entry['reported'] += $lines;
                        if (1 === preg_match('/DONE:(.+)/', $newOutput, $m)) {
                            $progressBar?->setMessage($m[1]);
                        }
                    }
                }

                try {
                    // Process enforces setIdleTimeout() only here — never in isRunning().
                    $entry['process']->checkTimeout();
                } catch (ProcessTimedOutException) {
                    $remaining = $entry['count'] - $entry['reported'];
                    if ($remaining > 0) {
                        $progressBar?->advance($remaining);
                    }

                    $errors[] = \sprintf('batch: worker killed after %gs without output, %d image(s) not processed', $this->workerIdleTimeout, $remaining);
                    unset($running[$key]);

                    continue;
                }

                if (! $entry['process']->isRunning()) {
                    $remaining = $entry['count'] - $entry['reported'];
                    if ($remaining > 0) {
                        $progressBar?->advance($remaining);
                    }

                    if (! $entry['process']->isSuccessful()) {
                        $stderr = trim($entry['process']->getErrorOutput());
                        $errors[] = 'batch: '.('' !== $stderr ? $stderr : 'exit code '.$entry['process']->getExitCode());
                    }

                    unset($running[$key]);
                }
            }

            unset($entry);

            if ([] !== $running) {
                usleep(50_000);
            }
        }

        $progressBar?->finish();

        if ($this->agentMode) {
            $this->reportAgentSummary($output, $staleCount - \count($errors), $preSkipped, $errors, $startTime);

            return [] === $errors ? Command::SUCCESS : Command::FAILURE;
        }

        $this->reportSummary($io, $staleCount - \count($errors), $preSkipped, \count($errors), $startTime);
        $this->reportErrors($errors, $io);

        return [] === $errors ? Command::SUCCESS : Command::FAILURE;
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

    /**
     * Emit a single compact JSON document for AI agents: counters only, no
     * progress/ANSI noise. Inspired by laravel/pao.
     *
     * @param string[] $errors
     */
    private function reportAgentSummary(OutputInterface $output, int $generated, int $skipped, array $errors, float $startTime): void
    {
        $errorCount = \count($errors);

        $this->writeAgentJson($output, [
            'tool' => 'pw:image:cache',
            'result' => 0 === $errorCount ? 'passed' : 'failed',
            'processed' => $generated + $skipped + $errorCount,
            'errors' => $errorCount,
            'generated' => $generated,
            'skipped' => $skipped,
            'duration_ms' => (int) round((microtime(true) - $startTime) * 1000),
        ]);
    }

    private function detectCpuCount(): int
    {
        return max(1, (int) (shell_exec('nproc') ?: 4));
    }
}
