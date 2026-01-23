<?php

namespace Pushword\StaticGenerator;

use LogicException;
use Psr\Log\LoggerInterface;
use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\App\AppPool;
use Pushword\StaticGenerator\Generator\GeneratorInterface;
use Pushword\StaticGenerator\Generator\PagesGenerator;
use Pushword\StaticGenerator\Generator\RedirectionManager;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Generate 1 App.
 */
final class StaticAppGenerator
{
    private bool $abortGeneration = false;

    /** @var array<string> */
    private array $errors = [];

    private bool $incremental = false;

    private ?OutputInterface $output = null;

    private ?Stopwatch $stopwatch = null;

    public function __construct(
        private readonly AppPool $apps,
        private readonly GeneratorBag $generatorBag,
        private readonly RedirectionManager $redirectionManager,
        private readonly LoggerInterface $logger,
        private readonly GenerationStateManager $stateManager,
    ) {
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
        $this->apps->switchCurrentApp($host)->get();

        $this->logger->info('Generating '.$host.'/'.$page);
        /** @var PagesGenerator $pagesGenerator */
        $pagesGenerator = $this->getGenerator(PagesGenerator::class);
        $pagesGenerator->generatePageBySlug($page);

        // Warning: no net if the generation failed !
    }

    /**
     * @throws RuntimeException
     * @throws LogicException
     */
    private function generateHost(string $host): void
    {
        $app = $this->apps->switchCurrentApp($host)->get();
        $originalStaticDir = $app->getStr('static_dir');
        $filesystem = new Filesystem();

        // In incremental mode, work directly in the static dir
        // In full mode, use a temporary directory for atomic swap
        if ($this->incremental && $this->stateManager->hasState($host) && file_exists($originalStaticDir)) {
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
                $filesystem->remove($originalStaticDir);
                $filesystem->rename($tempDir, $originalStaticDir);
                $filesystem->remove($tempDir);
            }
        }

        // Save state after successful generation
        if (! $this->abortGeneration) {
            $this->stateManager->setLastGenerationTime($host);
            $this->stateManager->save();
        }

        $this->abortGeneration = false;
    }

    private function runGenerators(AppConfig $app): void
    {
        foreach ($app->get('static_generators') as $generator) { // @phpstan-ignore-line
            if (! \is_string($generator)) {
                throw new LogicException();
            }

            $generatorInstance = $this->getGenerator($generator);
            if ($generatorInstance instanceof IncrementalGeneratorInterface) {
                $generatorInstance->setIncremental($this->incremental);
            }

            $generatorInstance->generate();
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
