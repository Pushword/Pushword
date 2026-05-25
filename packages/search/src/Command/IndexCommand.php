<?php

namespace Pushword\Search\Command;

use Pushword\Core\Site\SiteRegistry;
use Pushword\Search\Service\Indexer;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AsCommand(name: 'pw:search:index', description: 'Build the full-text search index (Loupe) for one or all hosts')]
#[AutoconfigureTag('console.command')]
final readonly class IndexCommand
{
    public function __construct(
        private Indexer $indexer,
        private SiteRegistry $siteRegistry,
    ) {
    }

    public function __invoke(
        OutputInterface $output,
        #[Argument(description: 'Limit indexing to a single host', name: 'host')]
        ?string $host = null,
    ): int {
        $hosts = null !== $host ? [$host] : $this->siteRegistry->getHosts();

        foreach ($hosts as $currentHost) {
            $count = $this->indexer->reindexHost($currentHost);
            $output->writeln(\sprintf('<info>%s</info>: indexed %d page(s).', $currentHost, $count));
        }

        return Command::SUCCESS;
    }
}
