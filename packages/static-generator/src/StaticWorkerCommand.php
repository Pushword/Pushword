<?php

declare(strict_types=1);

namespace Pushword\StaticGenerator;

use Pushword\StaticGenerator\Generator\PagesGenerator;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AsCommand(name: 'pw:static:worker', description: 'Internal worker for parallel static generation', hidden: true)]
#[AutoconfigureTag('console.command')]
final readonly class StaticWorkerCommand
{
    public function __construct(
        private StaticAppGenerator $staticAppGenerator,
        private GeneratorBag $generatorBag,
    ) {
    }

    public function __invoke(
        OutputInterface $output,
        #[Argument(name: 'host')]
        string $host,
        #[Option(description: 'Comma-separated page slugs to generate', name: 'slugs')]
        string $slugs = '',
        #[Option(description: 'Incremental mode', name: 'incremental', shortcut: 'i')]
        bool $incremental = false,
        #[Option(description: 'Worker state file path', name: 'state-file')]
        string $stateFile = '',
        #[Option(description: 'Worker redirections file path', name: 'redirections-file')]
        string $redirectionsFile = '',
        #[Option(description: 'Override static output directory', name: 'static-dir')]
        string $staticDir = '',
    ): int {
        if (in_array('', [$slugs, $stateFile, $redirectionsFile], true)) {
            $output->writeln('<error>Missing required options: --slugs, --state-file, --redirections-file</error>');

            return Command::FAILURE;
        }

        $slugList = explode(',', $slugs);

        /** @var PagesGenerator $pagesGenerator */
        $pagesGenerator = $this->generatorBag->get(PagesGenerator::class)->setStaticAppGenerator($this->staticAppGenerator);

        if ($incremental) {
            $pagesGenerator->setIncremental(true);
        }

        if ('' !== $staticDir) {
            $pagesGenerator->setStaticDirOverride($staticDir);
        }

        $pagesGenerator->generateSlugs($slugList, $stateFile, $redirectionsFile, $host);

        return Command::SUCCESS;
    }
}
