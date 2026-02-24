<?php

declare(strict_types=1);

namespace Pushword\Flat\Messenger;

use Psr\Log\LoggerInterface;
use Pushword\Flat\FlatFileSync;
use Pushword\Flat\Service\DeferredExportProcessor;
use Pushword\Flat\Service\GitAutoCommitter;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ConsumePendingExportHandler
{
    public function __construct(
        private DeferredExportProcessor $deferredExportProcessor,
        private FlatFileSync $flatFileSync,
        private GitAutoCommitter $gitAutoCommitter,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ConsumePendingExportMessage $message): void
    {
        $pending = $this->deferredExportProcessor->readPendingFlag();

        if (null === $pending) {
            return;
        }

        $entityTypes = $pending['entityTypes'];
        $hosts = $pending['hosts'];

        $entity = 1 === \count($entityTypes) ? $entityTypes[0] : 'all';

        $this->logger->info('ConsumePendingExport: entities={entities}, hosts={hosts}', [
            'entities' => implode(',', $entityTypes) ?: 'all',
            'hosts' => implode(',', $hosts) ?: 'all',
        ]);

        // Clear flag before export to avoid losing flags written during export
        $this->deferredExportProcessor->clearPendingFlag();

        $hostsToExport = [] !== $hosts ? $hosts : $this->flatFileSync->getHosts();
        foreach ($hostsToExport as $host) {
            $this->flatFileSync->export($host, force: true, entity: $entity);
        }

        if ($this->gitAutoCommitter->isEnabled()) {
            $this->gitAutoCommitter->commitIfChanges();
        }
    }
}
