<?php

namespace Pushword\Conversation\Flat;

use Pushword\Core\Component\App\AppPool;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;

#[AsCommand(name: 'pw:message:import', description: 'Convert conversation to flat file.')]
final readonly class MessageImportCommand
{
    public function __construct(
        private AppPool $apps,
        private ConversationSync $sync,
    ) {
    }

    public function __invoke(
        #[Argument(name: 'csvPath')]
        string $csvPath,
        #[Option(name: 'host')]
        ?string $host = null,
    ): int {
        if (null !== $host) {
            $this->apps->switchCurrentApp($host);
        }

        $this->sync->importer->importExternal($csvPath);

        return Command::SUCCESS;
    }
}
