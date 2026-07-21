<?php

namespace Pushword\Repurpose\Tests\Service;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Pushword\Repurpose\Service\FontPairingRegistry;
use Pushword\Repurpose\Service\FontResolver;

#[Group('integration')]
final class FontResolverTest extends TestCase
{
    private function resolver(?string $fontDir = null): FontResolver
    {
        return new FontResolver(new FontPairingRegistry(), $fontDir);
    }

    public function testBundledPairingResolvesToItsOwnFiles(): void
    {
        $resolver = $this->resolver();

        self::assertStringEndsWith('playfair-display.ttf', $resolver->headingFile('playfair-chivo'));
        self::assertStringEndsWith('chivo.ttf', $resolver->bodyFile('playfair-chivo'));
        self::assertSame('Playfair Display', $resolver->headingFamily('playfair-chivo'));
        self::assertTrue($resolver->isInstalled('playfair-chivo'));
    }

    public function testUninstalledPairingFallsBackToRoboto(): void
    {
        $resolver = $this->resolver();

        self::assertFalse($resolver->isInstalled('rozha-one-questrial'));
        self::assertStringEndsWith('roboto-bold.ttf', $resolver->headingFile('rozha-one-questrial'));
        self::assertStringEndsWith('roboto-regular.ttf', $resolver->bodyFile('rozha-one-questrial'));
        self::assertSame('Roboto', $resolver->headingFamily('rozha-one-questrial'));
    }

    public function testAppFontDirTakesPrecedenceAndCompletesAPairing(): void
    {
        $dir = sys_get_temp_dir().'/pw-fonts-'.uniqid();
        mkdir($dir);
        // Rozha One installed app-side; Questrial still missing.
        copy(__DIR__.'/../../src/Resources/font/roboto-regular.ttf', $dir.'/rozha-one.ttf');

        $resolver = $this->resolver($dir);

        self::assertSame($dir.'/rozha-one.ttf', $resolver->headingFile('rozha-one-questrial'));
        self::assertFalse($resolver->isInstalled('rozha-one-questrial'), 'both families must be present');

        copy(__DIR__.'/../../src/Resources/font/roboto-regular.ttf', $dir.'/questrial.ttf');
        self::assertTrue($resolver->isInstalled('rozha-one-questrial'));

        array_map(unlink(...), glob($dir.'/*') ?: []);
        rmdir($dir);
    }

    public function testUnknownPairingFallsBack(): void
    {
        $resolver = $this->resolver();

        self::assertStringEndsWith('roboto-bold.ttf', $resolver->headingFile('no-such-pairing'));
        self::assertFalse($resolver->isInstalled('no-such-pairing'));
    }
}
