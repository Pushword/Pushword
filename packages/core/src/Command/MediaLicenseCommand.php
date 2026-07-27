<?php

namespace Pushword\Core\Command;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Image\License\EmbeddedRightsReader;
use Pushword\Core\Image\License\MediaLicense;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Service\MediaStorageAdapter;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Applies the upload-time licensing decision to media that never went through an
 * upload hook — the whole existing library on day one, and any media again after the
 * app's `media_default_license_seed` changed (the seed is written into each media, so
 * a config change does not propagate on its own).
 */
#[AsCommand(name: 'pw:media:license', description: 'Seed image license metadata on existing media and report the ones carrying third-party rights')]
final class MediaLicenseCommand
{
    use AgentOutputTrait;

    private bool $agentMode = false;

    public function __construct(
        private readonly MediaRepository $mediaRepository,
        private readonly EntityManagerInterface $em,
        private readonly EmbeddedRightsReader $rightsReader,
        private readonly MediaStorageAdapter $mediaStorage,
        private readonly SiteRegistry $apps,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Option(description: 'Preview the decision without writing anything', name: 'dry-run')]
        bool $dryRun = false,
        #[Option(description: 'Apply the site license over third-party authorship (asserts the site owns the rights)', name: 'force', shortcut: 'f')]
        bool $force = false,
        #[Option(description: 'Re-decide media that already have a license state', name: 'all')]
        bool $all = false,
        #[Option(description: 'Output format: auto (compact JSON when an AI agent is detected), agent (force JSON), or text', name: 'format')]
        string $format = 'auto',
    ): int {
        $this->agentMode = $this->isAgentFormat($format);
        $io = new SymfonyStyle($input, $output);

        $seed = MediaLicense::normalizeSeed($this->apps->get()->getArray('media_default_license_seed'));

        if ([] === $seed && ! $this->agentMode) {
            $io->warning('No `media_default_license_seed` configured: nothing will be seeded, only embedded rights imported.');
        }

        // Without imagick, a file whose rights live only in XMP reads as unowned, and
        // seeding it would license somebody else's photo as the site's own.
        $canReadXmp = $this->rightsReader->canReadXmp();
        if (! $canReadXmp && ! $this->agentMode) {
            $io->warning('ext-imagick is unavailable: XMP cannot be read, so nothing will be seeded. Install it and re-run.');
        }

        $seeded = 0;
        $imported = 0;
        $skipped = 0;
        $exceptions = [];

        foreach ($this->mediaRepository->findAll() as $media) {
            if (! $media->isImage()) {
                continue;
            }

            if (! $all && MediaLicense::STATE_NONE !== $media->getLicenseState()) {
                ++$skipped;

                continue;
            }

            // A human assertion is never silently rewritten; --all is not a licence to
            // discard one, only to re-run the machine decision.
            if (MediaLicense::STATE_OVERRIDDEN === $media->getLicenseState()) {
                ++$skipped;

                continue;
            }

            $path = $this->mediaStorage->getLocalPath($media->getFileName());
            if (! is_file($path)) {
                ++$skipped;

                continue;
            }

            $rights = $this->rightsReader->read($path)->stripGeneratorMarkers();
            $isThirdParty = $rights->hasRightsValue();

            if ($isThirdParty) {
                $exceptions[] = [
                    'fileName' => $media->getFileName(),
                    'rights' => $rights->toCustomProperties(),
                ];
            }

            // A rights claim needs --force to override; a file that merely looks clean
            // needs the XMP read to have been trustworthy in the first place.
            $applySeed = [] !== $seed && ($isThirdParty ? $force : $canReadXmp);

            if ($isThirdParty && ! $applySeed) {
                ++$imported;
            } elseif ($applySeed) {
                ++$seeded;
            }

            if ($dryRun) {
                continue;
            }

            foreach (MediaLicense::KEYS as $key) {
                $media->removeCustomProperty($key);
            }

            foreach ($rights->toCustomProperties() as $key => $value) {
                $media->setCustomProperty($key, $value);
            }

            if (! $applySeed) {
                $media->setLicenseState($isThirdParty ? MediaLicense::STATE_THIRD_PARTY : MediaLicense::STATE_NONE);

                continue;
            }

            foreach ($seed as $key => $value) {
                $media->setCustomProperty($key, $value);
            }

            // --force over a rights claim is a human asserting ownership — the same act
            // as the admin's "apply the site license" button, hence the same state.
            $media->setLicenseState($isThirdParty ? MediaLicense::STATE_OVERRIDDEN : MediaLicense::STATE_SEEDED);
        }

        if (! $dryRun) {
            $this->em->flush();
        }

        if ($this->agentMode) {
            $this->writeAgentJson($output, [
                'command' => 'pw:media:license',
                'dryRun' => $dryRun,
                'force' => $force,
                'xmpReadable' => $canReadXmp,
                'seeded' => $seeded,
                'thirdParty' => $imported,
                'skipped' => $skipped,
                'exceptions' => $exceptions,
            ]);

            return Command::SUCCESS;
        }

        if ([] !== $exceptions) {
            $io->section('Third-party rights found — the site license was NOT applied');
            $io->listing(array_map(
                static fn (array $exception): string => \sprintf(
                    '%s — %s',
                    $exception['fileName'],
                    json_encode($exception['rights'], \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE),
                ),
                $exceptions,
            ));
            $io->comment('Re-run with --force to assert the site owns them.');
        }

        $io->success(\sprintf(
            '%d media seeded, %d left to their own rights, %d skipped.%s',
            $seeded,
            $imported,
            $skipped,
            $dryRun ? ' (dry run: nothing written)' : '',
        ));

        return Command::SUCCESS;
    }
}
