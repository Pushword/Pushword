<?php

namespace Pushword\Repurpose\Command;

use Pushword\Core\Command\AgentOutputTrait;
use Pushword\Repurpose\Service\FontPairingRegistry;
use Pushword\Repurpose\Service\FontResolver;
use Pushword\Repurpose\Service\GoogleFontDownloader;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Lists the font pairings with their real availability, and installs the missing
 * TTFs from Google Fonts into the app font dir (`repurpose.font_dir`, app-side so
 * a composer update never wipes them). Heading families are fetched at weight 700,
 * body-only families at 400 — one file serves a family across every pairing.
 */
#[AsCommand(
    name: 'pw:repurpose:fonts',
    description: 'List font pairings and their installed status, or install pairings ("--all" or keys) from Google Fonts.',
)]
final class RepurposeFontsCommand
{
    use AgentOutputTrait;

    private bool $agentMode = false;

    public function __construct(
        private readonly FontPairingRegistry $pairings,
        private readonly FontResolver $resolver,
        private readonly GoogleFontDownloader $downloader,
    ) {
    }

    /**
     * @param string[] $pairings
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Pairing keys to install (none: list every pairing and its installed status)')]
        array $pairings = [],
        #[Option(description: 'Install every pairing of the registry', name: 'all')]
        bool $all = false,
        #[Option(description: 'Output format: auto (compact JSON when an AI agent is detected), agent (force JSON), or text', name: 'format')]
        string $format = 'auto',
    ): int {
        $this->agentMode = $this->isAgentFormat($format);

        if ($all) {
            $pairings = FontPairingRegistry::keys();
        }

        if ([] === $pairings) {
            return $this->listPairings($io);
        }

        return $this->install($io, $pairings);
    }

    private function listPairings(SymfonyStyle $io): int
    {
        $rows = [];
        foreach ($this->pairings->all() as $key => $pairing) {
            $rows[] = [
                'key' => $key,
                'heading' => $pairing['heading'],
                'body' => $pairing['body'],
                'installed' => $this->resolver->isInstalled($key),
            ];
        }

        if ($this->agentMode) {
            $this->writeAgentJson($io, [
                'tool' => 'pw:repurpose:fonts',
                'result' => 'done',
                'fontDir' => $this->resolver->installDir(),
                'pairings' => $rows,
            ]);

            return Command::SUCCESS;
        }

        $io->table(
            ['pairing', 'heading', 'body', 'installed'],
            array_map(static fn (array $r): array => [$r['key'], $r['heading'], $r['body'], $r['installed'] ? 'yes' : 'no'], $rows),
        );
        $io->comment(\sprintf('Install one with: pw:repurpose:fonts <pairing> (fonts land in %s)', $this->resolver->installDir()));

        return Command::SUCCESS;
    }

    /**
     * @param string[] $keys
     */
    private function install(SymfonyStyle $io, array $keys): int
    {
        $installed = [];
        $skipped = [];
        $failed = [];

        foreach ($keys as $key) {
            $pairing = $this->pairings->get($key);
            if (null === $pairing) {
                $failed[] = ['pairing' => $key, 'reason' => 'unknown pairing'];

                continue;
            }

            foreach ([$pairing['heading'], $pairing['body']] as $family) {
                if ($this->resolver->hasFamily($family)) {
                    $skipped[] = $family;

                    continue;
                }

                $weight = FontPairingRegistry::isHeadingFamily($family) ? 700 : 400;
                $ttf = $this->downloader->download($family, $weight);
                if (null === $ttf) {
                    $failed[] = ['pairing' => $key, 'reason' => \sprintf('download failed for "%s"', $family)];

                    continue;
                }

                $this->write($family, $ttf);
                $installed[] = $family;
            }
        }

        $installed = array_values(array_unique($installed));
        $skipped = array_values(array_unique($skipped));

        if ($this->agentMode) {
            $this->writeAgentJson($io, [
                'tool' => 'pw:repurpose:fonts',
                'result' => [] === $failed ? 'done' : 'failed',
                'fontDir' => $this->resolver->installDir(),
                'installed' => $installed,
                'alreadyPresent' => $skipped,
                'failed' => $failed,
            ]);

            return [] === $failed ? Command::SUCCESS : Command::FAILURE;
        }

        foreach ($installed as $family) {
            $io->writeln(\sprintf('Installed <info>%s</info> → %s', $family, $this->resolver->installDir().'/'.$this->resolver->fileNameFor($family)));
        }

        foreach ($skipped as $family) {
            $io->writeln(\sprintf('<comment>%s</comment> already present, skipped.', $family));
        }

        foreach ($failed as $failure) {
            $io->error(\sprintf('%s: %s', $failure['pairing'], $failure['reason']));
        }

        if ([] === $failed) {
            $io->success('Fonts ready.');
        }

        return [] === $failed ? Command::SUCCESS : Command::FAILURE;
    }

    private function write(string $family, string $ttf): void
    {
        $dir = $this->resolver->installDir();
        if (! is_dir($dir)) {
            mkdir($dir, 0o775, true);
        }

        file_put_contents($dir.'/'.$this->resolver->fileNameFor($family), $ttf);
    }
}
