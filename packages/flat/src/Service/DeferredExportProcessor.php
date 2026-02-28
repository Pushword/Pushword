<?php

declare(strict_types=1);

namespace Pushword\Flat\Service;

use Pushword\Core\BackgroundTask\BackgroundTaskDispatcherInterface;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Flat\Messenger\ConsumePendingExportMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final class DeferredExportProcessor
{
    /** @var array<string, array{type: string, id: int|null, host: string|null, action: string}> */
    private array $queue = [];

    private bool $shutdownRegistered = false;

    public function __construct(
        private readonly string $varDir,
        private readonly BackgroundTaskDispatcherInterface $backgroundTaskDispatcher,
        private readonly ?MessageBusInterface $messageBus = null,
        private readonly bool $autoExportEnabled = true,
        private readonly int $debounceDelay = 120,
    ) {
    }

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

    public function processQueue(): void
    {
        if ([] === $this->queue) {
            return;
        }

        $entityTypes = array_unique(array_column($this->queue, 'type'));
        $hosts = array_unique(array_filter(array_column($this->queue, 'host'), static fn ($h): bool => null !== $h && '' !== $h));

        $this->queue = [];

        $this->writePendingFlag(array_values($entityTypes), array_values($hosts));

        if (null !== $this->messageBus) {
            $this->messageBus->dispatch(new ConsumePendingExportMessage(), [new DelayStamp($this->debounceDelay * 1000)]);
        } else {
            $this->backgroundTaskDispatcher->dispatch(
                'flat-deferred-export',
                ['pw:flat:sync', '--consume-pending'],
                'pw:flat:sync --consume-pending',
            );
        }
    }

    /** @return array<string, array{type: string, id: int|null, host: string|null, action: string}> */
    public function getQueue(): array
    {
        return $this->queue;
    }

    public function isEnabled(): bool
    {
        return $this->autoExportEnabled;
    }

    public function getPendingFlagPath(): string
    {
        return $this->varDir.'/flat-sync/export-pending.json';
    }

    /** @return array{entityTypes: string[], hosts: string[], dispatchAt: int}|null */
    public function readPendingFlag(): ?array
    {
        $path = $this->getPendingFlagPath();

        if (! file_exists($path)) {
            return null;
        }

        $content = file_get_contents($path);
        if (false === $content) {
            return null;
        }

        /** @var array{entityTypes: string[], hosts: string[], dispatchAt: int}|null $data */
        $data = json_decode($content, true);

        return \is_array($data) ? $data : null;
    }

    public function clearPendingFlag(): void
    {
        $path = $this->getPendingFlagPath();

        if (file_exists($path)) {
            unlink($path);
        }
    }

    /**
     * @param string[] $entityTypes
     * @param string[] $hosts
     */
    private function writePendingFlag(array $entityTypes, array $hosts): void
    {
        $path = $this->getPendingFlagPath();
        $dir = \dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        // Merge with existing flag if present
        $existing = $this->readPendingFlag();
        if (null !== $existing) {
            $entityTypes = array_values(array_unique([...$existing['entityTypes'], ...$entityTypes]));
            $hosts = array_values(array_unique([...$existing['hosts'], ...$hosts]));
        }

        file_put_contents($path, json_encode([
            'entityTypes' => $entityTypes,
            'hosts' => $hosts,
            'dispatchAt' => time() + $this->debounceDelay,
        ], \JSON_THROW_ON_ERROR));
    }

    private function registerShutdown(): void
    {
        if ($this->shutdownRegistered) {
            return;
        }

        $this->shutdownRegistered = true;

        register_shutdown_function([$this, 'processQueue']);
    }
}
