<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Tests\Service;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Pushword\Repurpose\Model\FontPairing;
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

    /**
     * A registry family keeps its suffix-free file at the weight of its registry
     * role; any other weight gets its own file.
     */
    public function testFileNamesCarryTheWeightOnlyOutsideTheRegistryRole(): void
    {
        $resolver = $this->resolver();

        self::assertSame('poppins.ttf', $resolver->fileNameFor('Poppins', 700));
        self::assertSame('poppins-400.ttf', $resolver->fileNameFor('Poppins', 400));
        self::assertSame('inter.ttf', $resolver->fileNameFor('Inter', 400));
        self::assertSame('big-shoulders-display.ttf', $resolver->fileNameFor('Big Shoulders Display', 700));
        self::assertSame('gabarito-700.ttf', $resolver->fileNameFor('Gabarito', 700));
        self::assertSame('gabarito.ttf', $resolver->fileNameFor('Gabarito', 400));
    }

    /**
     * The bundled poppins.ttf is bold, so a pairing that sets body text in
     * Poppins must not borrow it: body text would render bold.
     */
    public function testCustomPairingResolvesEachFamilyAtTheWeightOfItsRole(): void
    {
        $dir = sys_get_temp_dir().'/pw-fonts-'.uniqid();
        mkdir($dir);
        $pairing = new FontPairing('Gabarito', 'Poppins');
        $resolver = $this->resolver($dir);

        self::assertFalse($resolver->isInstalled($pairing));
        self::assertStringEndsWith('roboto-bold.ttf', $resolver->headingFile($pairing));
        self::assertStringEndsWith('roboto-regular.ttf', $resolver->bodyFile($pairing), 'never the bold bundled poppins.ttf');

        copy(__DIR__.'/../../src/Resources/font/roboto-bold.ttf', $dir.'/gabarito-700.ttf');
        copy(__DIR__.'/../../src/Resources/font/roboto-regular.ttf', $dir.'/poppins-400.ttf');

        self::assertTrue($resolver->isInstalled($pairing));
        self::assertSame($dir.'/gabarito-700.ttf', $resolver->headingFile($pairing));
        self::assertSame($dir.'/poppins-400.ttf', $resolver->bodyFile($pairing));
        self::assertSame('Gabarito', $resolver->headingFamily($pairing));
        self::assertStringEndsWith('/Resources/font/poppins.ttf', $resolver->headingFile(new FontPairing('Poppins', 'Inter')), 'a heading reuses the bundled bold file');

        array_map(unlink(...), glob($dir.'/*') ?: []);
        rmdir($dir);
    }

    public function testFacesListEachRoleWithItsWeight(): void
    {
        $resolver = $this->resolver();

        self::assertSame([
            'heading' => ['family' => 'Playfair Display', 'weight' => 700],
            'body' => ['family' => 'Chivo', 'weight' => 400],
        ], $resolver->faces('playfair-chivo'));
        self::assertSame([], $resolver->faces(null));
        self::assertSame([], $resolver->faces('no-such-pairing'));
    }

    public function testUnknownPairingFallsBack(): void
    {
        $resolver = $this->resolver();

        self::assertStringEndsWith('roboto-bold.ttf', $resolver->headingFile('no-such-pairing'));
        self::assertFalse($resolver->isInstalled('no-such-pairing'));
    }
}
