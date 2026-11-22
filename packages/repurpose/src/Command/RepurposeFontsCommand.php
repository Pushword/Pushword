<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Command;

use Pushword\Core\Command\AgentOutputTrait;
use Pushword\Repurpose\Model\FontPairing;
use Pushword\Repurpose\Service\FontInstaller;
use Pushword\Repurpose\Service\FontPairingRegistry;
use Pushword\Repurpose\Service\FontResolver;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Lists the font pairings with their real availability, and installs missing
 * TTFs from Google Fonts into the app font dir (`repurpose.font_dir`, app-side so
 * a composer update never wipes them): registry pairings by key, or the two
 * families of a custom `{heading, body}` pairing. Each family is fetched at the
 * weight of its role, 700 for headings and 400 for body text.
 */
#[AsCommand(
    name: 'pw:repurpose:fonts',
    description: 'List font pairings and their installed status, or install pairings ("--all" or keys) or custom families (--heading, --body) from Google Fonts.',
)]
final class RepurposeFontsCommand
{
    use AgentOutputTrait;

    private bool $agentMode = false;

    public function __construct(
        private readonly FontPairingRegistry $pairings,
        private readonly FontResolver $resolver,
        private readonly FontInstaller $installer,
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
        #[Option(description: 'Google Fonts family to install for headings (weight 700), as in a custom fontPairing', name: 'heading')]
        ?string $heading = null,
        #[Option(description: 'Google Fonts family to install for body text (weight 400), as in a custom fontPairing', name: 'body')]
        ?string $body = null,
        #[Option(description: 'Output format: auto (compact JSON when an AI agent is detected), agent (force JSON), or text', name: 'format')]
        string $format = 'auto',
    ): int {
        $this->agentMode = $this->isAgentFormat($format);

        if ($all) {
            $pairings = FontPairingRegistry::keys();
        }

        if ([] === $pairings && null === $heading && null === $body) {
            return $this->listPairings($io);
        }

        return $this->install($io, $pairings, $heading, $body);
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
        $io->comment(\sprintf('Install one with: pw:repurpose:fonts <pairing>, or any Google Fonts family with --heading=… --body=… (fonts land in %s)', $this->resolver->installDir()));

        return Command::SUCCESS;
    }

    /**
     * @param string[] $keys
     */
    private function install(SymfonyStyle $io, array $keys, ?string $heading, ?string $body): int
    {
        $faces = [];
        $failed = [];

        foreach ($keys as $key) {
            if (null === $this->pairings->get($key)) {
                $failed[] = ['pairing' => $key, 'reason' => 'unknown pairing'];

                continue;
            }

            foreach ($this->resolver->faces($key) as $face) {
                $faces[] = ['source' => $key, ...$face];
            }
        }

        foreach (['heading' => [$heading, FontResolver::HEADING_WEIGHT], 'body' => [$body, FontResolver::BODY_WEIGHT]] as $role => [$family, $weight]) {
            if (null === $family) {
                continue;
            }

            if (1 !== preg_match(FontPairing::FAMILY, $family)) {
                $failed[] = ['pairing' => '--'.$role, 'reason' => \sprintf('invalid family name "%s"', $family)];

                continue;
            }

            $faces[] = ['source' => '--'.$role, 'family' => $family, 'weight' => $weight];
        }

        $installed = [];
        $skipped = [];
        foreach ($faces as $face) {
            if ($this->resolver->hasFace($face['family'], $face['weight'])) {
                $skipped[] = $face['family'];

                continue;
            }

            if (! $this->installer->install($face['family'], $face['weight'])) {
                $failed[] = ['pairing' => $face['source'], 'reason' => \sprintf('download failed for "%s"', $face['family'])];

                continue;
            }

            $installed[$face['family']] = $this->resolver->installDir().'/'.$this->resolver->fileNameFor($face['family'], $face['weight']);
        }

        $skipped = array_values(array_unique($skipped));

        if ($this->agentMode) {
            $this->writeAgentJson($io, [
                'tool' => 'pw:repurpose:fonts',
                'result' => [] === $failed ? 'done' : 'failed',
                'fontDir' => $this->resolver->installDir(),
                'installed' => array_keys($installed),
                'alreadyPresent' => $skipped,
                'failed' => $failed,
            ]);

            return [] === $failed ? Command::SUCCESS : Command::FAILURE;
        }

        foreach ($installed as $family => $path) {
            $io->writeln(\sprintf('Installed <info>%s</info> → %s', $family, $path));
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
}
