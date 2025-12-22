<?php

namespace Pushword\StaticGenerator;

use DateTimeImmutable;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Manages generation state for incremental static site generation.
 * Tracks last generation timestamps per host to enable incremental updates.
 */
final class GenerationStateManager
{
    private const string STATE_FILE = '.static-generation-state.json';

    /** @var array<string, array{lastGeneration: string, pages: array<string, array{generatedAt: string, pageUpdatedAt: string}>}> */
    private array $state = [];

    private bool $loaded = false;

    public function __construct(
        private readonly string $projectDir,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    private function getStateFilePath(): string
    {
        return $this->projectDir.'/'.self::STATE_FILE;
    }

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->loaded = true;
        $path = $this->getStateFilePath();

        if (! file_exists($path)) {
            $this->state = [];

            return;
        }

        $content = file_get_contents($path);
        if (false === $content) {
            $this->state = [];

            return;
        }

        /** @var array<string, array{lastGeneration: string, pages: array<string, array{generatedAt: string, pageUpdatedAt: string}>}>|null $decoded */
        $decoded = json_decode($content, true);
        $this->state = $decoded ?? [];
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

        if (! isset($this->state[$host])) {
            $this->state[$host] = ['lastGeneration' => '', 'pages' => []];
        }

        $this->state[$host]['lastGeneration'] = $time->format(\DateTimeInterface::ATOM);
    }

    public function getPageState(string $host, string $slug): ?DateTimeImmutable
    {
        $this->load();

        if (! isset($this->state[$host]['pages'][$slug]['generatedAt'])) {
            return null;
        }

        return new DateTimeImmutable($this->state[$host]['pages'][$slug]['generatedAt']);
    }

    public function setPageState(string $host, string $slug, DateTimeImmutable $pageUpdatedAt): void
    {
        $this->load();

        if (! isset($this->state[$host])) {
            $this->state[$host] = ['lastGeneration' => '', 'pages' => []];
        }

        $now = new DateTimeImmutable();
        $this->state[$host]['pages'][$slug] = [
            'generatedAt' => $now->format(\DateTimeInterface::ATOM),
            'pageUpdatedAt' => $pageUpdatedAt->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * Check if a page needs regeneration based on its updatedAt timestamp.
     */
    public function needsRegeneration(string $host, string $slug, DateTimeImmutable $pageUpdatedAt): bool
    {
        $this->load();

        if (! isset($this->state[$host]['pages'][$slug])) {
            return true;
        }

        $storedUpdatedAt = $this->state[$host]['pages'][$slug]['pageUpdatedAt'];

        return $pageUpdatedAt->format(\DateTimeInterface::ATOM) !== $storedUpdatedAt;
    }

    /**
     * Remove pages from state that no longer exist (were deleted).
     *
     * @param string[] $currentSlugs List of slugs that currently exist
     */
    public function cleanupDeletedPages(string $host, array $currentSlugs): void
    {
        $this->load();

        if (! isset($this->state[$host]['pages'])) {
            return;
        }

        $slugSet = array_flip($currentSlugs);
        foreach (array_keys($this->state[$host]['pages']) as $slug) {
            if (! isset($slugSet[$slug])) {
                unset($this->state[$host]['pages'][$slug]);
            }
        }
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
}
