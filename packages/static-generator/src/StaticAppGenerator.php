<?php

namespace Pushword\StaticGenerator;

use LogicException;
use Psr\Log\LoggerInterface;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Site\SiteConfig;
use Pushword\Core\Site\SiteRegistry;
use Pushword\StaticGenerator\Cache\PageCacheGeneratorInterface;
use Pushword\StaticGenerator\DependencyInjection\Configuration;
use Pushword\StaticGenerator\Event\StaticPostGenerateEvent;
use Pushword\StaticGenerator\Event\StaticPreGenerateEvent;
use Pushword\StaticGenerator\Generator\GeneratorInterface;
use Pushword\StaticGenerator\Generator\PagesGenerator;
use Pushword\StaticGenerator\Generator\RedirectionManager;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
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

    public function __construct(
        private readonly SiteRegistry $apps,
        private readonly GeneratorBag $generatorBag,
        private readonly RedirectionManager $redirectionManager,
        private readonly LoggerInterface $logger,
        private readonly GenerationStateManager $stateManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly PageRepository $pageRepository,
        private readonly string $projectDir,
    ) {
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
        $i = 0;
        foreach ($this->apps->getHosts() as $host) {
            if (null !== $hostToGenerate && $hostToGenerate !== $host) {
                continue;
            }

            $this->generateHost($host);
            $this->redirectionManager->reset();
            ++$i;
        }

        return $i;
    }

    public function generatePage(string $host, string $page): void
    {
        $app = $this->apps->switchSite($host)->get();

        if (self::isCacheMode($app)) {
            $app->staticDir = $this->getCacheDir($app); // @phpstan-ignore-line
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
            $this->runGenerators($app);
        } else {
            // Full generation: use temp dir + atomic swap
            $tempDir = $originalStaticDir.'~';
            $app->staticDir = $tempDir; // @phpstan-ignore-line

            $filesystem->remove($tempDir);
            $filesystem->mkdir($tempDir);

            $this->runGenerators($app);

            // Restore original staticDir before atomic swap
            $app->staticDir = $originalStaticDir; // @phpstan-ignore-line

            if (! $this->abortGeneration) {
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
            $this->stateManager->setLastGenerationTime($host);
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
        $cacheDir = $this->getCacheDir($app);
        $app->staticDir = $cacheDir; // @phpstan-ignore-line

        new Filesystem()->mkdir($cacheDir);

        $this->eventDispatcher->dispatch(
            new StaticPreGenerateEvent($app, $cacheDir, $this->incremental),
        );

        $this->runGenerators($app);

        if (! $this->abortGeneration) {
            $this->stateManager->setLastGenerationTime($app->getMainHost());
            $this->stateManager->save();
        }

        $this->eventDispatcher->dispatch(
            new StaticPostGenerateEvent($app, $cacheDir, $this->incremental, $this->errors),
        );

        $this->abortGeneration = false;
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

    private function runGenerators(SiteConfig $app): void
    {
        $slugs = array_map(
            static fn (Page $page): string => $page->getSlug(),
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
        $processes = [];

        foreach ($chunks as $i => $chunk) {
            if ([] === $chunk) {
                continue;
            }

            $stateFile = $stateDir.'/.static-worker-'.$i.'.json';
            $redirectionsFile = $stateDir.'/.static-worker-'.$i.'-redirections.json';

            $cmd = [
                'php', 'bin/console', 'pw:static:worker', $host,
                '--slugs='.implode(',', $chunk),
                '--state-file='.$stateFile,
                '--redirections-file='.$redirectionsFile,
                '--static-dir='.$app->getStr('static_dir'),
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
