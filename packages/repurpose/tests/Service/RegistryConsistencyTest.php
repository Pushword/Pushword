<?php

namespace Pushword\Repurpose\Tests\Service;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Pushword\Repurpose\Service\FontPairingRegistry;
use Pushword\Repurpose\Service\FontResolver;
use Pushword\Repurpose\Service\FormatRegistry;
use Pushword\Repurpose\Service\NetworkRegistry;

/**
 * Cross-registry data invariants. The registries are static const tables, so a
 * typo — a network pointing at a format id that does not exist, an orphan format,
 * a bundled pairing that was renamed — would otherwise only surface as a broken
 * render at runtime. This test is the only guard.
 */
#[Group('integration')]
final class RegistryConsistencyTest extends TestCase
{
    public function testEveryNetworkFormatExistsInTheFormatRegistry(): void
    {
        $formats = FormatRegistry::ids();

        foreach (NetworkRegistry::NETWORKS as $network => $config) {
            foreach ($config['formats'] as $format) {
                self::assertContains($format, $formats, \sprintf('network "%s" references unknown format "%s"', $network, $format));
            }
        }
    }

    public function testEveryFormatIsReferencedByAtLeastOneNetwork(): void
    {
        $referenced = [];
        foreach (NetworkRegistry::NETWORKS as $config) {
            foreach ($config['formats'] as $format) {
                $referenced[$format] = true;
            }
        }

        foreach (FormatRegistry::ids() as $format) {
            self::assertArrayHasKey($format, $referenced, \sprintf('format "%s" is orphaned — no network exposes it', $format));
        }
    }

    public function testEveryNetworkExportModeIsKnown(): void
    {
        foreach (NetworkRegistry::NETWORKS as $network => $config) {
            self::assertContains($config['export'], ['images', 'pdf'], \sprintf('network "%s" has an unknown export mode', $network));
        }
    }

    public function testEveryNetworkHasAPlausibleMobileFeedWidth(): void
    {
        $registry = new NetworkRegistry();

        foreach (NetworkRegistry::keys() as $network) {
            $width = $registry->mobileWidth($network);
            self::assertGreaterThanOrEqual(150, $width, \sprintf('network "%s" feedMobile', $network));
            self::assertLessThanOrEqual(600, $width, \sprintf('network "%s" feedMobile', $network));
        }

        self::assertSame(390, $registry->mobileWidth('myspace'), 'unknown networks fall back to a sane width');
    }

    /**
     * Drives which weight `pw:repurpose:fonts` downloads: 700 for families that
     * headline somewhere, 400 for body-only ones.
     */
    public function testIsHeadingFamilyReflectsThePairingTable(): void
    {
        self::assertTrue(FontPairingRegistry::isHeadingFamily('Playfair Display'));
        // Poppins headlines poppins-inter even though it is a body elsewhere.
        self::assertTrue(FontPairingRegistry::isHeadingFamily('Poppins'));
        self::assertFalse(FontPairingRegistry::isHeadingFamily('Roboto'), 'Roboto is body-only');
        self::assertFalse(FontPairingRegistry::isHeadingFamily('No Such Family'));
    }

    public function testEveryFormatHasPositiveDimensions(): void
    {
        $registry = new FormatRegistry();

        foreach (FormatRegistry::ids() as $id) {
            self::assertGreaterThan(0, $registry->width($id), \sprintf('format "%s" width', $id));
            self::assertGreaterThan(0, $registry->height($id), \sprintf('format "%s" height', $id));
        }
    }

    public function testBundledFontPairingsResolveToRealPairings(): void
    {
        $registry = new FontPairingRegistry();
        $keys = FontPairingRegistry::keys();
        $bundled = array_values(array_filter($keys, $registry->isBundled(...)));

        self::assertNotEmpty($bundled, 'at least one pairing must ship bundled as the fallback');

        foreach ($bundled as $key) {
            self::assertContains($key, $keys, \sprintf('isBundled("%s") points at a key the registry does not list', $key));
            $pairing = $registry->get($key);
            self::assertIsArray($pairing, \sprintf('bundled pairing "%s" is missing from the registry', $key));
            self::assertNotSame('', $pairing['heading']);
            self::assertNotSame('', $pairing['body']);
        }
    }

    /**
     * `bundled: true` is a promise the TTFs ship with the package — a pairing
     * marked bundled with no font on disk silently renders in Roboto, lying to
     * every agent that trusted the flag.
     */
    public function testEveryBundledPairingHasItsFontFilesOnDisk(): void
    {
        $registry = new FontPairingRegistry();
        $resolver = new FontResolver($registry);

        foreach (FontPairingRegistry::keys() as $key) {
            if (! $registry->isBundled($key)) {
                continue;
            }

            self::assertTrue(
                $resolver->isInstalled($key),
                \sprintf('pairing "%s" is marked bundled but its TTFs are not in src/Resources/font/', $key),
            );
        }
    }
}
