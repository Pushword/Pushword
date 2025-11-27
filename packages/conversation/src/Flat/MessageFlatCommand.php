<?php

namespace Pushword\Conversation\Flat;

use Pushword\Core\Component\App\AppPool;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'pw:message:flat', description: 'Convert conversation to flat file.')]
final readonly class MessageFlatCommand
{
    public function __construct(
        private AppPool $apps,
        private ConversationSync $sync,
    ) {
    }

    public function __invoke(
        OutputInterface $output,
        #[Argument(name: 'host')]
        ?string $host,
        #[Option(name: 'force', shortcut: 'f')]
        string $force = 'sync'
    ): int {
        $host ??= $this->apps->getMainHost();
        $output->writeln($this->sync->mustImport($host) ? 'Must import conversation' : 'Must export conversation');
        $output->writeln('-- '.$host);

        if ('import' === $force) {
            $output->writeln('-- Importing conversation');
            $this->sync->importer->import($host);
        } elseif ('export' === $force) {
            $output->writeln('-- Exporting conversation');
            $this->sync->exporter->export($host);
        } else {
            $output->writeln('-- Syncing conversation');
            $this->sync->sync($host);
        }

        $output->writeln('Sync completed.');

        return Command::SUCCESS;
    }
}
