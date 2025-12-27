<?php

namespace Pushword\Flat\Command;

use Pushword\Flat\FlatFileSync;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'pw:flat:export',
)]
final readonly class FlatFileExportCommand
{
    public function __construct(
        private FlatFileSync $flatFileSync,
    ) {
    }

    public function __invoke(
        OutputInterface $output,
        #[Argument(name: 'host')]
        ?string $host,
        #[Argument(name: 'exportDir')]
        ?string $exportDir,
        #[Option(name: 'force', shortcut: 'f')]
        bool $force = false,
        #[Option(description: 'Skip adding IDs to markdown files and CSV indexes', name: 'skip-id')]
        bool $skipId = false,
    ): int {
        $output->writeln('Export will start in few seconds...');

        $this->flatFileSync->export($host, $exportDir, $force, $skipId);

        $output->writeln('Export completed.');

        if (null !== $exportDir && '' !== $exportDir) {
            $output->writeln('Results stored in '.$exportDir);
        }

        return Command::SUCCESS;
    }
}
