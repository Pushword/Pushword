<?php

declare(strict_types=1);

namespace Pushword\Flat\Service;

use Pushword\Core\BackgroundTask\BackgroundTaskDispatcherInterface;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;

/**
 * Handles deferred export of entities after admin modifications.
 * Queues entities and triggers export after the request completes.
 */
final class DeferredExportProcessor
{
    /** @var array<string, array{type: string, id: int|null, host: string|null, action: string}> */
    private array $queue = [];

    private bool $shutdownRegistered = false;

    public function __construct(
        private readonly BackgroundTaskDispatcherInterface $backgroundTaskDispatcher,
        private readonly string $projectDir,
        private readonly bool $useBackgroundProcess = true,
        private readonly bool $autoExportEnabled = true,
    ) {
    }

    /**
     * Queue an entity for deferred export.
     */
    public function queue(Page|Media $entity, string $action): void
    {
        if (! $this->autoExportEnabled) {
            return;
        }

        $type = $entity instanceof Page ? 'page' : 'media';
        $id = $entity->id;
        $host = $entity instanceof Page ? $entity->host : null;

        // Use unique key to avoid duplicates
        $key = $type.'_'.($id ?? 'new');

        $this->queue[$key] = [
            'type' => $type,
            'id' => $id,
            'host' => $host,
            'action' => $action,
        ];

        $this->registerShutdown();
    }

    /**
     * Process all queued exports.
     */
    public function processQueue(): void
    {
        if ([] === $this->queue) {
            return;
        }

        // Determine which entity types need to be exported
        $entityTypes = array_unique(array_column($this->queue, 'type'));
        $hosts = array_unique(array_filter(array_column($this->queue, 'host'), static fn ($h): bool => null !== $h && '' !== $h));

        // Clear the queue
        $this->queue = [];

        if ($this->useBackgroundProcess) {
            $this->runBackgroundExport($entityTypes, $hosts);
        } else {
            $this->runInlineExport($entityTypes, $hosts);
        }
    }

    /**
     * Get the current queue for inspection.
     *
     * @return array<string, array{type: string, id: int|null, host: string|null, action: string}>
     */
    public function getQueue(): array
    {
        return $this->queue;
    }

    /**
     * Check if auto export is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->autoExportEnabled;
    }

    /**
     * @param string[] $entityTypes
     * @param string[] $hosts
     */
    private function runBackgroundExport(array $entityTypes, array $hosts): void
    {
        // Build the command arguments
        $entityArg = 1 === \count($entityTypes) && isset($entityTypes[0]) ? $entityTypes[0] : 'all';
        $host = 1 === \count($hosts) && isset($hosts[0]) ? $hosts[0] : null;

        $commandParts = $this->buildExportCommandParts($entityArg, $host);

        $this->backgroundTaskDispatcher->dispatch('flat-deferred-export', $commandParts, 'pw:flat:sync');
    }

    /**
     * @param string[] $entityTypes
     * @param string[] $hosts
     */
    private function runInlineExport(array $entityTypes, array $hosts): void
    {
        // For inline export, we trigger the sync command directly
        // This is a fallback when background processes aren't available
        $entityArg = 1 === \count($entityTypes) && isset($entityTypes[0]) ? $entityTypes[0] : 'all';
        $host = 1 === \count($hosts) && isset($hosts[0]) ? $hosts[0] : null;

        $command = $this->buildExportCommand($entityArg, $host);

        exec($command.' > /dev/null 2>&1');
    }

    private function buildExportCommand(string $entity, ?string $host): string
    {
        return implode(' ', array_map(escapeshellarg(...), $this->buildExportCommandParts($entity, $host)));
    }

    /**
     * @return string[]
     */
    private function buildExportCommandParts(string $entity, ?string $host): array
    {
        $consolePath = $this->projectDir.'/bin/console';
        $parts = ['php', $consolePath, 'pw:flat:sync', '--mode=export'];

        if (null !== $host) {
            $parts[] = $host;
        }

        $parts[] = '--entity='.$entity;
        $parts[] = '--force';

        return $parts;
    }

    private function registerShutdown(): void
    {
        if ($this->shutdownRegistered) {
            return;
        }

        $this->shutdownRegistered = true;

        // Use register_shutdown_function to process after response is sent
        register_shutdown_function([$this, 'processQueue']);
    }
}
