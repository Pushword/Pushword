<?php

namespace Pushword\Core\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * The installer offers these files at `composer create-project` time and writes nothing
 * when the answer is no. This command is the way back — and the way in for a project
 * created before the question existed.
 */
#[AsCommand(name: 'pw:docker:init', description: 'Add the Docker/FrankenPHP setup (Dockerfile, compose files) to this project')]
final readonly class DockerInitCommand
{
    public function __construct(
        private string $projectDir,
        private string $dockerSkeletonDir,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Option(description: 'Overwrite files that already exist', name: 'force', shortcut: 'f')]
        bool $force = false,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $filesystem = new Filesystem();

        $written = [];
        $skipped = [];

        $finder = new Finder()->files()->in($this->dockerSkeletonDir)->ignoreDotFiles(false)->sortByName();
        foreach ($finder as $file) {
            $relativePath = $file->getRelativePathname();
            $target = $this->projectDir.'/'.$relativePath;

            if (! $force && file_exists($target)) {
                $skipped[] = $relativePath;

                continue;
            }

            $filesystem->copy($file->getPathname(), $target, true);
            if (str_ends_with($relativePath, '.sh')) {
                $filesystem->chmod($target, 0o755);
            }

            $written[] = $relativePath;
        }

        // The entrypoint seeds an unseeded volume with the starter content and an admin
        // account. This project has both already — it was installed on the host — so
        // mark it, or a bind-mounted development container would seed it a second time.
        $filesystem->dumpFile(
            $this->projectDir.'/var/.pushword-seeded',
            "Written by pw:docker:init: this project was installed and seeded on the host.\n"
        );

        if ([] === $written) {
            $io->warning('Everything was already there. Run with --force to overwrite.');

            return Command::SUCCESS;
        }

        $io->success('Docker setup added.');
        $io->listing($written);

        if ([] !== $skipped) {
            $io->note('Left alone (already present, use --force to overwrite): '.implode(', ', $skipped));
        }

        $io->writeln([
            ' Start it with <info>docker compose up --build</info>, then open <info>http://localhost:8080</info>.',
            ' Production goes through <info>compose.prod.yaml</info> — see <info>https://pushword.piedweb.com/docker</info>.',
            '',
        ]);

        return Command::SUCCESS;
    }
}
