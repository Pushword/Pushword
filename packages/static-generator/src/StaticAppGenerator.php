<?php

namespace Pushword\StaticGenerator;

use LogicException;
use Psr\Log\LoggerInterface;
use Pushword\Core\Cache\RenderEpoch;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Site\SiteConfig;
use Pushword\Core\Site\SiteRegistry;
use Pushword\StaticGenerator\Cache\PageCacheGeneratorInterface;
use Pushword\StaticGenerator\DependencyInjection\Configuration;
use Pushword\StaticGenerator\Event\StaticPostGenerateEvent;
use Pushword\StaticGenerator\Event\StaticPreGenerateEvent;
use Pushword\StaticGenerator\Generator\CompressionAlgorithm;
use Pushword\StaticGenerator\Generator\GeneratorInterface;
use Pushword\StaticGenerator\Generator\PagesGenerator;
use Pushword\StaticGenerator\Generator\RedirectionManager;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Process\Process;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Generate 1 App.
 */
final class StaticAppGenerator implements PageCacheGeneratorInterface
{
    private bool $abortGeneration = false;

    /** @var array<string> */
    private array $errors = [];

    private bool $incremental = false;

    private ?OutputInterface $output = null;

    private ?Stopwatch $stopwatch = null;

    private int $workers = 0;

    /** @var array<string, string> */
    private array $sampledEpochs = [];

    private ?LockFactory $lockFactory = null;

    public function __construct(
        private readonly SiteRegistry $apps,
        private readonly GeneratorBag $generatorBag,
        private readonly RedirectionManager $redirectionManager,
        private readonly LoggerInterface $logger,
        private readonly GenerationStateManager $stateManager,
        private readonly RenderEpoch $renderEpoch,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly PageRepository $pageRepository,
        private readonly string $projectDir,
        private readonly string $environment,
    ) {
    }

    /**
     * Epoch sampled once per host per generate() call. Pages are stamped with the
     * sample and the sample is recorded as sweptEpoch on success — never the
     * then-current value, so a bump landing mid-generation always reads as stale
     * on the next pass instead of being silently absorbed.
     */
    public function getSampledRenderEpoch(string $host): string
    {
        return $this->sampledEpochs[$host] ??= $this->renderEpoch->get($host);
    }

    public function setWorkers(int $workers): void
    {
        $this->workers = $workers;
    }

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function setStopwatch(Stopwatch $stopwatch): void
    {
        $this->stopwatch = $stopwatch;
    }

    public function getStopwatch(): ?Stopwatch
    {
        return $this->stopwatch;
    }

    public function writeln(string $message): void
    {
        $this->output?->writeln($message);
    }

    /**
     * @param ?string $hostToGenerate if null, generate all apps
     * @param bool    $incremental    if true, only regenerate changed pages
     *
     * @return int the number of site generated
     */
    public function generate(?string $hostToGenerate = null, bool $incremental = false): int
    {
        $this->incremental = $incremental;
        // A messenger worker reuses this service across messages: a sample kept
        // from a previous sweep would stamp post-bump renders with a pre-bump
        // epoch and the debounce would loop forever.
        $this->sampledEpochs = [];
        $i = 0;
        foreach ($this->apps->getHosts() as $host) {
            if (null !== $hostToGenerate && $hostToGenerate !== $host) {
                continue;
            }

            // Serialize whole-host runs (message handler, cron, manual): each run
            // read-modify-writes the shared state file. Flock releases on process
            // death, so no stale-lock TTL is needed. Workers spawned by this run
            // are not serialized — they write per-worker files the parent merges.
            $lock = $this->getLockFactory()->createLock('pw-static-'.$host);
            $lock->acquire(true);

            try {
                $this->generateHost($host);
                $this->redirectionManager->reset();
            } finally {
                $lock->release();
            }

            ++$i;
        }

        return $i;
    }

    private function getLockFactory(): LockFactory
    {
        return $this->lockFactory ??= new LockFactory(new FlockStore());
    }

    public function generatePage(string $host, string $page): void
    {
        $app = $this->apps->switchSite($host)->get();

        if (self::isCacheMode($app)) {
            $app->setCustomProperty('static_dir', $this->getCacheDir($app));
        }

        $this->logger->info('Generating '.$host.'/'.$page);
        /** @var PagesGenerator $pagesGenerator */
        $pagesGenerator = $this->getGenerator(PagesGenerator::class);
        $pagesGenerator->generatePageBySlug($page);
    }

    public static function isCacheMode(SiteConfig $app): bool
    {
        return 'static' === $app->getStr('cache', 'none');
    }

    public function getCacheDir(SiteConfig $app): string
    {
        // Tests isolate the cache dir per ParaTest worker to avoid races on public/cache/{host}/.
        $testVarDir = getenv('PUSHWORD_TEST_VAR_DIR');
        if (false !== $testVarDir && '' !== $testVarDir) {
            return $testVarDir.'/cache/'.$app->getMainHost();
        }

        return $this->projectDir.'/public/cache/'.$app->getMainHost();
    }

    /**
     * @throws RuntimeException
     * @throws LogicException
     */
    private function generateHost(string $host): void
    {
        $app = $this->apps->switchSite($host)->get();

        // Sample before any rendering: pages must never be stamped with an epoch
        // newer than the content they were rendered from.
        $sampledEpoch = $this->getSampledRenderEpoch($app->getMainHost());

        if (self::isCacheMode($app)) {
            $this->generateHostInCacheMode($app);

            return;
        }

        $originalStaticDir = $app->getStr('static_dir');
        $filesystem = new Filesystem();

        $this->cleanupStaleTempDirs($originalStaticDir, $filesystem);

        $this->eventDispatcher->dispatch(
            new StaticPreGenerateEvent($app, $originalStaticDir, $this->incremental),
        );

        // In incremental mode, work directly in the static dir
        // In full mode, use a temporary directory for atomic swap
        if ($this->incremental && $this->stateManager->hasState($host) && $filesystem->exists($originalStaticDir)) {
            // Incremental mode: update in place
            $this->logger->info('Incremental generation for '.$host);
            $currentSlugs = $this->runGenerators($app);
            $this->lintGeneratedOutput($originalStaticDir);
        } else {
            // Full generation: use temp dir + atomic swap
            $tempDir = $originalStaticDir.'~';
            $app->setCustomProperty('static_dir', $tempDir);

            $filesystem->remove($tempDir);
            $filesystem->mkdir($tempDir);

            $currentSlugs = $this->runGenerators($app);

            // Restore original staticDir before atomic swap
            $app->setCustomProperty('static_dir', $originalStaticDir);

            // Lint BEFORE the swap: a poisoned export must never replace the
            // last good one (a lint error aborts, so the site keeps serving it).
            if (! $this->abortGeneration) {
                $this->lintGeneratedOutput($tempDir);
            }

            if (! $this->abortGeneration) {
                // Held pages are skipped by the generators, so the temp dir lacks
                // their output. Carry the previously published files over so the
                // atomic swap keeps serving the held version instead of dropping it.
                $this->carryOverHeldPages($originalStaticDir, $tempDir, $app, $filesystem);

                $backupDir = $originalStaticDir.'~~';
                $filesystem->remove($backupDir);

                if ($filesystem->exists($originalStaticDir)) {
                    $filesystem->rename($originalStaticDir, $backupDir);
                }

                $filesystem->rename($tempDir, $originalStaticDir);
                $filesystem->remove($backupDir);
            }
        }

        // Save state after successful generation
        if (! $this->abortGeneration) {
            // After the swap the pruning only trims state entries; in-place
            // (incremental) builds also lose the files of vanished pages here.
            $this->pruneDeletedPages($app, $originalStaticDir, $currentSlugs);
            $this->stateManager->setLastGenerationTime($host);
            $this->stateManager->setSweptEpoch($app->getMainHost(), $sampledEpoch);
            $this->stateManager->save();
        }

        $this->eventDispatcher->dispatch(
            new StaticPostGenerateEvent($app, $originalStaticDir, $this->incremental, $this->errors),
        );

        $this->abortGeneration = false;
    }

    /**
     * Cache mode writes directly into public/cache/{host}/ — no temp dir, no atomic swap.
     * Operators run `pw:cache:clear` for a hard reset; per-page refreshes land in place.
     */
    private function generateHostInCacheMode(SiteConfig $app): void
    {
        $sampledEpoch = $this->getSampledRenderEpoch($app->getMainHost());
        $cacheDir = $this->getCacheDir($app);
        $app->setCustomProperty('static_dir', $cacheDir);

        new Filesystem()->mkdir($cacheDir);

        $this->eventDispatcher->dispatch(
            new StaticPreGenerateEvent($app, $cacheDir, $this->incremental),
        );

        $currentSlugs = $this->runGenerators($app);

        if (! $this->abortGeneration) {
            // Cache mode always writes in place: deleted pages must be pruned
            // on every build, not just incremental ones.
            $this->pruneDeletedPages($app, $cacheDir, $currentSlugs);
            $this->stateManager->setLastGenerationTime($app->getMainHost());
            $this->stateManager->setSweptEpoch($app->getMainHost(), $sampledEpoch);
            $this->stateManager->save();
        }

        $this->eventDispatcher->dispatch(
            new StaticPostGenerateEvent($app, $cacheDir, $this->incremental, $this->errors),
        );

        $this->abortGeneration = false;
    }

    /**
     * Copy the previously generated files of each held page from a source dir
     * into a destination dir, so a rebuild keeps serving their published version
     * instead of dropping them (the generators skip held pages). Used by the full
     * (temp + swap) rebuild and by `pw:cache:clear` after wiping the cache dir.
     * Paginated feed pages keep only their primary files (extra pager files are
     * rebuilt on release).
     */
    public function carryOverHeldPages(string $originalStaticDir, string $tempDir, SiteConfig $app, Filesystem $filesystem): void
    {
        if (! $filesystem->exists($originalStaticDir)) {
            return;
        }

        $heldPages = $this->pageRepository->getPublishedPages($app->getMainHost(), [['holdPublicationAt', 'IS NOT', null]]);
        foreach ($heldPages as $page) {
            $relative = $this->staticRelativePathFor($page);
            $bases = [$relative];
            if (str_ends_with($relative, '.html')) {
                $bases[] = substr($relative, 0, -5).'.xml'; // children feed variant
            }

            foreach ($bases as $base) {
                foreach (CompressionAlgorithm::fileSuffixes() as $suffix) {
                    $source = $originalStaticDir.'/'.$base.$suffix;
                    if ($filesystem->exists($source)) {
                        $filesystem->copy($source, $tempDir.'/'.$base.$suffix, true);
                    }
                }
            }
        }
    }

    /** Mirrors PageGenerator::generateFilePath for the static-dir-relative path. */
    private function staticRelativePathFor(Page $page): string
    {
        return $this->staticRelativePathForSlug($page->slug);
    }

    /** @param string $slug the raw entity slug, as keyed in the generation state */
    private function staticRelativePathForSlug(string $slug): string
    {
        if ('homepage' === $slug || '' === $slug) {
            return 'index.html';
        }

        if (str_ends_with($slug, '.json') || str_ends_with($slug, '.xml')) {
            return $slug;
        }

        return $slug.'.html';
    }

    private function cleanupStaleTempDirs(string $staticDir, Filesystem $filesystem): void
    {
        foreach ([$staticDir.'~', $staticDir.'~~'] as $dir) {
            if ($filesystem->exists($dir) && is_dir($dir) && filemtime($dir) < time() - 3600) {
                $this->logger->info('Removing stale temp directory: '.$dir);
                $filesystem->remove($dir);
            }
        }
    }

    /** @return string[] the published slugs of the host — the witness list for pruning */
    private function runGenerators(SiteConfig $app): array
    {
        $slugs = array_map(
            static fn (Page $page): string => $page->slug,
            $this->pageRepository->getPublishedPages($app->getMainHost()),
        );
        $workerCount = WorkerCountResolver::resolve($this->workers, \count($slugs));

        $generators = self::isCacheMode($app)
            ? Configuration::DEFAULT_GENERATOR_CACHE
            : $app->get('static_generators');

        foreach ($generators as $generator) { // @phpstan-ignore-line
            if (! \is_string($generator)) {
                throw new LogicException();
            }

            if (PagesGenerator::class === $generator && $workerCount > 1) {
                $this->generatePagesInParallel($app, $slugs, $workerCount);

                continue;
            }

            $generatorInstance = $this->getGenerator($generator);
            if ($generatorInstance instanceof IncrementalGeneratorInterface) {
                $generatorInstance->setIncremental($this->incremental);
            }

            $generatorInstance->generate();
        }

        return $slugs;
    }

    /**
     * In-place builds (incremental and cache mode) never sweep the output of
     * pages deleted or unpublished since the last run — only the full build's
     * atomic swap does. Drop their state entries and their generated files.
     * Pager files (`slug/2.html`) are left to the next full build: the pager
     * directory also holds child pages' output.
     *
     * @param string[] $currentSlugs
     */
    private function pruneDeletedPages(SiteConfig $app, string $staticDir, array $currentSlugs): void
    {
        $host = $app->getMainHost();
        $filesystem = new Filesystem();

        foreach ($this->stateManager->cleanupDeletedPages($host, $currentSlugs) as $slug) {
            $relativePath = $this->staticRelativePathForSlug($slug);
            $bases = [$relativePath];
            if (str_ends_with($relativePath, '.html')) {
                $bases[] = substr($relativePath, 0, -5).'.xml'; // children feed variant
            }

            foreach ($bases as $base) {
                foreach (CompressionAlgorithm::fileSuffixes() as $suffix) {
                    $filesystem->remove($staticDir.'/'.$base.$suffix);
                }
            }

            $this->writeln(\sprintf('<comment>Removed</comment> %s/%s (no longer published)', $host, '' === $slug ? 'index' : $slug));
        }
    }

    /**
     * @param string[] $slugs
     */
    private function generatePagesInParallel(SiteConfig $app, array $slugs, int $workerCount): void
    {
        $host = $app->getMainHost();

        $this->writeln(\sprintf('<info>Generating %d pages with %d workers</info>', \count($slugs), $workerCount));

        // Round-robin distribution for balanced load
        $chunks = array_fill(0, $workerCount, []);
        foreach ($slugs as $i => $slug) {
            $chunks[$i % $workerCount][] = $slug;
        }

        $stateDir = $this->projectDir.'/var';

        // CLI opcache is per-process, so each worker recompiles the whole
        // codebase unless compiled scripts persist on disk. The file cache is
        // shared across workers and successive builds (~-18% per fresh worker
        // pass); the flags are inert when the CLI has no opcache extension.
        // The cache also outlives composer update, so timestamp validation is
        // forced on: a host ini tuning it off would silently keep serving
        // entries compiled from the pre-update sources.
        $opcacheDir = $stateDir.'/cache/opcache';
        new Filesystem()->mkdir($opcacheDir);

        $processes = [];

        foreach ($chunks as $i => $chunk) {
            if ([] === $chunk) {
                continue;
            }

            $stateFile = $stateDir.'/.static-worker-'.$i.'.json';
            $redirectionsFile = $stateDir.'/.static-worker-'.$i.'-redirections.json';

            $cmd = [
                'php',
                // Both switches, not just the CLI one: a host shipping opcache
                // disabled (`opcache.enable=0`) still loads the extension, so
                // `enable_cli` alone leaves it inactive — the file cache stays
                // empty and every worker recompiles from source, silently.
                '-d', 'opcache.enable=1',
                '-d', 'opcache.enable_cli=1',
                '-d', 'opcache.file_cache='.$opcacheDir,
                '-d', 'opcache.validate_timestamps=1',
                'bin/console', 'pw:static:worker', $host,
                '--slugs='.implode(',', $chunk),
                '--state-file='.$stateFile,
                '--redirections-file='.$redirectionsFile,
                '--static-dir='.$app->getStr('static_dir'),
                // Workers must run in the parent's environment: without this they
                // re-resolve APP_ENV from .env and would read another database.
                '--env='.$this->environment,
                '--no-debug',
            ];

            if ($this->incremental) {
                $cmd[] = '--incremental';
            }

            $process = new Process($cmd, $this->projectDir);
            $process->setTimeout(null);
            $process->start();
            $processes[$i] = ['process' => $process, 'stateFile' => $stateFile, 'redirectionsFile' => $redirectionsFile];
        }

        $this->waitForWorkers($processes);
    }

    /**
     * @param array<int, array{process: Process, stateFile: string, redirectionsFile: string}> $workers
     */
    private function waitForWorkers(array $workers): void
    {
        $running = true;

        while ($running) {
            $running = false;

            foreach ($workers as $i => $worker) {
                $process = $worker['process'];
                $running = $running || $process->isRunning();

                $output = $process->getIncrementalOutput();
                if ('' !== $output) {
                    foreach (explode("\n", rtrim($output, "\n")) as $line) {
                        $this->writeln(\sprintf('<comment>[W%d]</comment> %s', $i, $line));
                    }
                }

                $process->getIncrementalErrorOutput(); // drain stderr
            }

            if ($running) {
                usleep(100_000);
            }
        }

        foreach ($workers as $i => $worker) {
            $process = $worker['process'];

            if (! $process->isSuccessful()) {
                $this->setError(\sprintf('Worker %d failed (exit %d): %s', $i, $process->getExitCode() ?? -1, $process->getErrorOutput()));
            }

            $this->stateManager->mergeFromFile($worker['stateFile']);
            $this->redirectionManager->importFromFile($worker['redirectionsFile']);
        }
    }

    /** See StaticOutputLinter — every configured host (aliases included) is a forbidden first path segment. */
    private function lintGeneratedOutput(string $dir): void
    {
        $hosts = array_merge(...array_map(
            static fn (SiteConfig $siteConfig): array => $siteConfig->hosts,
            array_values($this->apps->getAll()),
        ));

        foreach (StaticOutputLinter::lint($dir, $hosts) as $error) {
            $this->setError($error);
        }
    }

    private function getGenerator(string $name): GeneratorInterface
    {
        return $this->generatorBag->get($name)->setStaticAppGenerator($this);
    }

    public function setError(string $errorMessage): void
    {
        $this->errors[] = $errorMessage;
        $this->abortGeneration = true;
    }

    /**
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function isIncremental(): bool
    {
        return $this->incremental;
    }

    public function getStateManager(): GenerationStateManager
    {
        return $this->stateManager;
    }
}
