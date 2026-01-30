<?php

declare(strict_types=1);

namespace Pushword\Flat\Command;

use Pushword\Core\Site\SiteRegistry;
use Pushword\Flat\Sync\ConflictResolver;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'pw:flat:conflicts:clear',
    description: 'Remove all conflict backup files.'
)]
final readonly class FlatConflictsClearCommand
{
    public function __construct(
        private ConflictResolver $conflictResolver,
        private SiteRegistry $apps,
    ) {
    }

    public function __invoke(
        OutputInterface $output,
        #[Argument(description: 'The host to clear conflicts for (optional)', name: 'host')]
        ?string $host = null,
        #[Option(description: 'List files without deleting them', name: 'dry', shortcut: 'd')]
        bool $dry = false,
    ): int {
        $resolvedHost = $host ?? $this->apps->getMainHost();
        $conflicts = $this->conflictResolver->findUnresolvedConflicts($resolvedHost);

        if ([] === $conflicts) {
            $output->writeln('<info>No conflict files found.</info>');

            return Command::SUCCESS;
        }

        $output->writeln(\sprintf('<comment>Found %d conflict file(s):</comment>', \count($conflicts)));

        foreach ($conflicts as $file) {
            $output->writeln('  - '.$file);
        }

        if ($dry) {
            $output->writeln('<comment>Dry run - no files deleted.</comment>');

            return Command::SUCCESS;
        }

        $deleted = $this->conflictResolver->clearConflictFiles($resolvedHost);

        $output->writeln(\sprintf('<info>Deleted %d conflict file(s).</info>', \count($deleted)));

        return Command::SUCCESS;
    }
}
