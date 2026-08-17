<?php

namespace Pushword\Core\Command;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\BackgroundTask\BackgroundCommand;
use Pushword\Core\Entity\Media;
use Pushword\Core\Image\ImageCacheGenerator;
use Pushword\Core\Image\ImageCacheManager;
use Pushword\Core\Image\ImageReader;
use Pushword\Core\Image\ImageScratchSweeper;
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
     * The line a worker prints per image, and what the parent counts. A worker prints
     * no summary, so the prefix is also the only way the outcome of a single image
     * reaches the run.
     */
    private const string MARKER_DONE = 'DONE:';

    private const string MARKER_FAIL = 'FAIL:';

    /**
     * Killed after this long *without output*, not this long in total: a worker
     * prints a line per image, so silence means stuck, while a wall-clock cap would
     * kill a large batch that is progressing perfectly well. Property, not const,
     * so tests can shrink it through reflection.
     */
    private float $workerIdleTimeout = 300;

    private bool $agentMode = false;

    /** @var array{swept: int, empty: int} */
    private array $scratch = ['swept' => 0, 'empty' => 0];

    public function __construct(
        private readonly MediaRepository $mediaRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ImageCacheGenerator $imageCacheGenerator,
        private readonly ImageCacheManager $imageCacheManager,
        private readonly ImageReader $imageReader,
        private readonly ImageScratchSweeper $imageScratchSweeper,
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

            // Only the lock holder sweeps: the parallel workers run with --no-lock,
            // and each one repeating the walk would burn the media tree N times over
            // for nothing. Holding the lock is also what keeps the sweep off another
            // run's live scratch files, on top of the age bound.
            $this->scratch = $this->imageScratchSweeper->sweep();
            if ($this->scratch['swept'] > 0 && ! $this->agentMode) {
                $io->info(\sprintf('Swept %d stale scratch file(s), %d empty.', $this->scratch['swept'], $this->scratch['empty']));
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
        $generated = 0;
        $seen = 0;

        foreach ($medias as $media) {
            if ($media->isImage()) {
                $progressBar?->setMessage($media->getPath());

                $failure = null;

                try {
                    if ($this->imageCacheGenerator->generateCache($media, $force)) {
                        ++$generated;
                    } else {
                        ++$skipped;
                    }
                } catch (Throwable $exception) {
                    // Flattened: the parent reads one image per line, and a multi-line
                    // message would arrive as several unparsable ones.
                    $failure = strtr($exception->getMessage(), ["\r" => ' ', "\n" => ' ']);
                    $errors[] = $media->getFileName().': '.$failure;
                }

                if ($isWorker && ! $this->agentMode) {
                    $output->writeln(null === $failure
                        ? self::MARKER_DONE.$media->getFileName()
                        : self::MARKER_FAIL.$media->getFileName().': '.$failure);
                }
            } else {
                // A non-image never reaches generateCache(), so it lands in none of the
                // three buckets — which is why $generated is tallied and not derived.
                // The old count - skipped - errors charged every one of them to
                // "processed", and a converged library reported that as work each night.
                $this->imageCacheManager->ensurePublicSymlink($media);
            }

            $progressBar?->advance();

            if (0 === ++$seen % 50) {
                $this->entityManager->flush();
            }
        }

        $this->entityManager->flush();
        $progressBar?->finish();

        if ($this->agentMode) {
            $this->reportAgentSummary($output, $generated, $skipped, $errors, $startTime);

            return [] === $errors ? Command::SUCCESS : Command::FAILURE;
        }

        if (! $isWorker) {
            $this->reportSummary($io, $generated, $skipped, \count($errors), $startTime);
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

        /** @var array<int, array{process: Process, count: int, done: int, failed: int, buffer: string}> $running */
        $running = [];
        $errors = [];
        $processed = 0;
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
                $running[] = ['process' => $process, 'count' => \count($chunk), 'done' => 0, 'failed' => 0, 'buffer' => ''];
            }

            foreach ($running as $key => &$entry) {
                $this->readWorkerLines($entry, $errors, $progressBar, final: false);

                try {
                    // Process enforces setIdleTimeout() only here — never in isRunning().
                    $entry['process']->checkTimeout();
                } catch (ProcessTimedOutException) {
                    $unreported = $this->advanceOverUnreported($entry, $progressBar);
                    $errors[] = \sprintf('batch: worker killed after %gs without output, %d image(s) not processed', $this->workerIdleTimeout, $unreported);
                    $processed += $entry['done'];
                    unset($running[$key]);

                    continue;
                }

                if (! $entry['process']->isRunning()) {
                    // The worker may have written its last lines between the read above
                    // and its exit; they are images it did, so they must be read here.
                    $this->readWorkerLines($entry, $errors, $progressBar, final: true);
                    $unreported = $this->advanceOverUnreported($entry, $progressBar);
                    $batchError = $this->describeIncompleteBatch($entry, $unreported);
                    if (null !== $batchError) {
                        $errors[] = $batchError;
                    }

                    $processed += $entry['done'];
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
            $this->reportAgentSummary($output, $processed, $preSkipped, $errors, $startTime);

            return [] === $errors ? Command::SUCCESS : Command::FAILURE;
        }

        $this->reportSummary($io, $processed, $preSkipped, \count($errors), $startTime);
        $this->reportErrors($errors, $io);

        return [] === $errors ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Consume the complete lines a worker has written since the last read: one per
     * image it finished, DONE: or FAIL:. A partial line is kept in the buffer —
     * getIncrementalOutput() cuts wherever the pipe was drained, and counting
     * markers in the raw chunk loses (or doubles) an image split across two reads.
     *
     * @param array{process: Process, count: int, done: int, failed: int, buffer: string} $entry
     * @param string[]                                                                    $errors
     */
    private function readWorkerLines(array &$entry, array &$errors, ?ProgressBar $progressBar, bool $final): void
    {
        $entry['buffer'] .= $entry['process']->getIncrementalOutput();
        if ($final && '' !== $entry['buffer']) {
            $entry['buffer'] .= "\n"; // the process is over: whatever is left is a whole line
        }

        $advance = 0;
        while (false !== ($eol = strpos($entry['buffer'], "\n"))) {
            $line = rtrim(substr($entry['buffer'], 0, $eol), "\r");
            $entry['buffer'] = substr($entry['buffer'], $eol + 1);

            if (str_starts_with($line, self::MARKER_DONE)) {
                ++$entry['done'];
                ++$advance;
                $progressBar?->setMessage(substr($line, \strlen(self::MARKER_DONE)));
            } elseif (str_starts_with($line, self::MARKER_FAIL)) {
                ++$entry['failed'];
                ++$advance;
                $errors[] = substr($line, \strlen(self::MARKER_FAIL));
            }
        }

        if ($advance > 0) {
            $progressBar?->advance($advance);
        }
    }

    /**
     * What a finished batch has to answer for, or null when it accounted for every
     * image and exited clean. Silence is not success: a worker exiting 0 without a
     * line per image did none of the missing ones — the batch travels as one
     * comma-joined argument, so a fileName holding a comma resolves to nothing, and
     * the whole batch used to be counted as done.
     *
     * @param array{process: Process, count: int, done: int, failed: int, buffer: string} $entry
     */
    private function describeIncompleteBatch(array $entry, int $unreported): ?string
    {
        // A per-image failure already carries its own message and explains a non-zero
        // exit; only an otherwise unexplained one needs a reason.
        $reason = null;
        if (! $entry['process']->isSuccessful() && 0 === $entry['failed']) {
            $stderr = trim($entry['process']->getErrorOutput());
            $reason = '' !== $stderr ? $stderr : 'exit code '.$entry['process']->getExitCode();
        }

        if ($unreported > 0) {
            return \sprintf(
                'batch: %d image(s) never reported by the worker%s',
                $unreported,
                null !== $reason ? ' ('.$reason.')' : '',
            );
        }

        return null !== $reason ? 'batch: '.$reason : null;
    }

    /**
     * Images of the batch the worker never accounted for. The progress bar still
     * has to reach its max, but they are not work anyone did.
     *
     * @param array{process: Process, count: int, done: int, failed: int, buffer: string} $entry
     */
    private function advanceOverUnreported(array $entry, ?ProgressBar $progressBar): int
    {
        $unreported = $entry['count'] - $entry['done'] - $entry['failed'];
        if ($unreported > 0) {
            $progressBar?->advance($unreported);
        }

        return max(0, $unreported);
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
            // Always emitted, including as 0: agents are the population that runs this
            // command in bulk, and a count that only appears when it is non-zero is a
            // count nobody can graph.
            'scratch_swept' => $this->scratch['swept'],
            'scratch_empty' => $this->scratch['empty'],
            'duration_ms' => (int) round((microtime(true) - $startTime) * 1000),
        ]);
    }

    private function detectCpuCount(): int
    {
        return max(1, (int) (shell_exec('nproc') ?: 4));
    }
}
