<?php

namespace Pushword\StaticGenerator;

use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Manages generation state for incremental static site generation.
 * Tracks last generation timestamps per host to enable incremental updates.
 */
final class GenerationStateManager implements ResetInterface
{
    private const string STATE_FILE = '.static-generation-state.json';

    /** @var array<string, array{lastGeneration: string, sweptEpoch?: string, pages: array<string, array{generatedAt: string, pageUpdatedAt: string, epoch?: string}>}> */
    private array $state = [];

    private bool $loaded = false;

    public function __construct(
        private readonly string $projectDir,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    private function getStateFilePath(): string
    {
        // Tests isolate the state file per ParaTest worker to avoid races on the
        // shared var/ dir (mirrors StaticAppGenerator::getCacheDir).
        $testVarDir = getenv('PUSHWORD_TEST_VAR_DIR');
        if (false !== $testVarDir && '' !== $testVarDir) {
            return $testVarDir.'/'.self::STATE_FILE;
        }

        return $this->projectDir.'/var/'.self::STATE_FILE;
    }

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->loaded = true;
        $path = $this->getStateFilePath();

        if (! $this->filesystem->exists($path)) {
            $this->state = [];

            return;
        }

        $content = $this->filesystem->readFile($path);

        /** @var array<string, array{lastGeneration: string, sweptEpoch?: string, pages: array<string, array{generatedAt: string, pageUpdatedAt: string, epoch?: string}>}>|null $decoded */
        $decoded = json_decode($content, true);
        $this->state = $decoded ?? [];
    }

    /**
     * Drop the in-memory copy and re-read from disk. Long-lived processes
     * (messenger workers) call this before comparing against state another
     * process may have written since.
     */
    public function reload(): void
    {
        $this->loaded = false;
        $this->load();
    }

    /**
     * Drop the memoized copy at every request boundary (autoconfigure tags this
     * `kernel.reset`). Under a long-lived worker the container outlives the
     * request, so without this each worker kept the copy it read first and served
     * a stale `lastGeneration` — a poll answering "completed" with the timestamp
     * of the *previous* generation until the worker recycled.
     *
     * A CLI pass keeps its cache: nothing resets services mid-command, and every
     * mutation is saved inside the same generateHost() call that made it.
     */
    public function reset(): void
    {
        $this->loaded = false;
        $this->state = [];
    }

    public function save(): void
    {
        $this->filesystem->dumpFile(
            $this->getStateFilePath(),
            json_encode($this->state, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR)
        );
    }

    public function getLastGenerationTime(string $host): ?DateTimeImmutable
    {
        $this->load();

        if (! isset($this->state[$host]['lastGeneration'])) {
            return null;
        }

        return new DateTimeImmutable($this->state[$host]['lastGeneration']);
    }

    public function setLastGenerationTime(string $host, ?DateTimeImmutable $time = null): void
    {
        $this->load();

        $time ??= new DateTimeImmutable();

        $this->state[$host] ??= ['lastGeneration' => '', 'pages' => []];

        $this->state[$host]['lastGeneration'] = $time->format(DateTimeInterface::ATOM);
    }

    public function getPageState(string $host, string $slug): ?DateTimeImmutable
    {
        $this->load();

        if (! isset($this->state[$host]['pages'][$slug]['generatedAt'])) {
            return null;
        }

        return new DateTimeImmutable($this->state[$host]['pages'][$slug]['generatedAt']);
    }

    public function setPageState(string $host, string $slug, DateTimeImmutable $pageUpdatedAt, string $epoch): void
    {
        $this->load();

        $this->state[$host] ??= ['lastGeneration' => '', 'pages' => []];

        $now = new DateTimeImmutable();
        $this->state[$host]['pages'][$slug] = [
            'generatedAt' => $now->format(DateTimeInterface::ATOM),
            'pageUpdatedAt' => $pageUpdatedAt->format(DateTimeInterface::ATOM),
            'epoch' => $epoch,
        ];
    }

    /**
     * A page is stale when its updatedAt changed (a Page write) or when the host
     * epoch moved since it was generated (anything else: snippet, media, template,
     * another page). Entries written before the epoch existed read as stale.
     */
    public function needsRegeneration(string $host, string $slug, DateTimeImmutable $pageUpdatedAt, string $currentEpoch): bool
    {
        $this->load();

        if (! isset($this->state[$host]['pages'][$slug])) {
            return true;
        }

        $stored = $this->state[$host]['pages'][$slug];
        if ($pageUpdatedAt->format(DateTimeInterface::ATOM) !== $stored['pageUpdatedAt']) {
            return true;
        }

        return $currentEpoch !== ($stored['epoch'] ?? null);
    }

    /**
     * Epoch sampled at the start of the last completed generation of this host.
     * Always the sampled value, never the epoch current at completion: a bump
     * landing mid-generation must leave the host looking unswept.
     */
    public function getSweptEpoch(string $host): ?string
    {
        $this->load();

        return $this->state[$host]['sweptEpoch'] ?? null;
    }

    public function setSweptEpoch(string $host, string $epoch): void
    {
        $this->load();

        $this->state[$host] ??= ['lastGeneration' => '', 'pages' => []];

        $this->state[$host]['sweptEpoch'] = $epoch;
    }

    /**
     * Remove pages from state that no longer exist (were deleted or unpublished).
     *
     * @param string[] $currentSlugs List of slugs that currently exist
     *
     * @return string[] the slugs that were dropped
     */
    public function cleanupDeletedPages(string $host, array $currentSlugs): array
    {
        $this->load();

        if (! isset($this->state[$host]['pages'])) {
            return [];
        }

        $slugSet = array_flip($currentSlugs);
        $removedSlugs = [];
        foreach (array_keys($this->state[$host]['pages']) as $slug) {
            if (! isset($slugSet[$slug])) {
                unset($this->state[$host]['pages'][$slug]);
                $removedSlugs[] = $slug;
            }
        }

        return $removedSlugs;
    }

    /**
     * Clear all state for a host (used when forcing full regeneration).
     */
    public function clearHost(string $host): void
    {
        $this->load();
        unset($this->state[$host]);
    }

    /**
     * Check if incremental generation is possible (state file exists and has data for host).
     */
    public function hasState(string $host): bool
    {
        $this->load();

        return isset($this->state[$host]) && '' !== $this->state[$host]['lastGeneration'];
    }

    public function mergeFromFile(string $workerStateFile): void
    {
        if (! file_exists($workerStateFile)) {
            return;
        }

        $this->load();

        /** @var array<string, array{pages: array<string, array{generatedAt: string, pageUpdatedAt: string, epoch?: string}>}> $workerState */
        $workerState = json_decode((string) file_get_contents($workerStateFile), true) ?? [];

        foreach ($workerState as $host => $hostData) {
            $this->state[$host] ??= ['lastGeneration' => '', 'pages' => []];

            foreach ($hostData['pages'] as $slug => $pageState) {
                $this->state[$host]['pages'][$slug] = $pageState;
            }
        }

        unlink($workerStateFile);
    }
}
