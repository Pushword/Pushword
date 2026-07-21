<?php

namespace Pushword\Repurpose\Tests\Service;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Pushword\Repurpose\Service\FontPairingRegistry;
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
}
