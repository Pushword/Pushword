<?php

declare(strict_types=1);

namespace Pushword\Flat\Command;

use Pushword\Flat\Sync\UserSync;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'pw:flat:user-sync',
    description: 'Sync users between config/users.yaml and database (bidirectional)',
)]
final class FlatUserSyncCommand extends Command
{
    public function __construct(
        private readonly UserSync $userSync,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->userSync->setOutput($output);
        $this->userSync->import();

        $io->table(
            ['Exported to YAML', 'Imported to DB', 'Updated', 'Skipped'],
            [[
                $this->userSync->getExportedCount(),
                $this->userSync->getImportedCount(),
                $this->userSync->getUpdatedCount(),
                $this->userSync->getSkippedCount(),
            ]]
        );

        return Command::SUCCESS;
    }
}
