<?php

namespace Pushword\StaticGenerator\Cache;

use Pushword\Core\Site\SiteRegistry;
use Pushword\StaticGenerator\StaticAppGenerator;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(name: 'pw:cache:clear', description: 'Clear (and warm) the static page cache for sites running in cache:static mode')]
#[AutoconfigureTag('console.command')]
final readonly class CacheClearCommand
{
    public function __construct(
        private StaticAppGenerator $staticAppGenerator,
        private SiteRegistry $apps,
    ) {
    }

    public function __invoke(
        OutputInterface $output,
        #[Argument(description: 'Target a single host (default: all cache-enabled hosts)', name: 'host')]
        ?string $host = null,
        #[Option(description: 'Clear only — skip warm-up render pass', name: 'no-warmup')]
        bool $noWarmup = false,
    ): int {
        $filesystem = new Filesystem();
        $hostsToProcess = null === $host ? $this->apps->getHosts() : [$host];
        $processedCount = 0;

        foreach ($hostsToProcess as $targetHost) {
            $app = $this->apps->switchSite($targetHost)->get();

            if (! StaticAppGenerator::isCacheMode($app)) {
                if (null !== $host) {
                    $output->writeln('<comment>'.$targetHost.': cache mode is not "static" — skipping</comment>');
                }

                continue;
            }

            $cacheDir = $this->staticAppGenerator->getCacheDir($app);
            $output->writeln('<info>Clearing cache: '.$cacheDir.'</info>');

            // Move the live cache aside instead of deleting it outright: held pages
            // get no fresh output (the generators skip them), so their frozen files
            // must be carried over from here or visitors would fall through to the
            // live draft. The backup is removed once the carry-over is done.
            $backupDir = $cacheDir.'~clearing';
            $filesystem->remove($backupDir);
            if ($filesystem->exists($cacheDir)) {
                $filesystem->rename($cacheDir, $backupDir);
            }

            $filesystem->mkdir($cacheDir);
            ++$processedCount;

            if (! $noWarmup) {
                $output->writeln('<info>Warming cache for '.$targetHost.'</info>');
                $this->staticAppGenerator->setOutput($output);
                $this->staticAppGenerator->generate($targetHost);
            }

            $this->staticAppGenerator->carryOverHeldPages($backupDir, $cacheDir, $app, $filesystem);
            $filesystem->remove($backupDir);
        }

        if (0 === $processedCount) {
            $output->writeln('<comment>No sites with cache:static found.</comment>');
        }

        return [] !== $this->staticAppGenerator->getErrors() ? Command::FAILURE : Command::SUCCESS;
    }
}
