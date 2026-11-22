<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Tests\Service;

use PHPUnit\Framework\TestCase;
use Pushword\Repurpose\Model\FontPairing;
use Pushword\Repurpose\Service\FontInstaller;
use Pushword\Repurpose\Service\FontPairingRegistry;
use Pushword\Repurpose\Service\FontResolver;
use Pushword\Repurpose\Service\GoogleFontDownloader;
use Pushword\Repurpose\Tests\GoogleFontsMock;
use Symfony\Component\Filesystem\Filesystem;

final class FontInstallerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/pw-font-installer-'.uniqid();
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->dir);
    }

    private function installer(GoogleFontsMock $google): FontInstaller
    {
        return new FontInstaller(
            new FontResolver(new FontPairingRegistry(), $this->dir),
            new GoogleFontDownloader($google->client()),
            new Filesystem(),
        );
    }

    public function testAMissingFaceIsDownloadedOnceThenServedFromTheFontDir(): void
    {
        $google = new GoogleFontsMock();
        $installer = $this->installer($google);

        self::assertTrue($installer->install('Gabarito', 700));
        self::assertFileExists($this->dir.'/gabarito-700.ttf');
        self::assertSame([
            'https://fonts.googleapis.com/css2?family=Gabarito:wght@700',
            'https://fonts.gstatic.com/s/font/v1/font.ttf',
        ], $google->takeRequests());

        self::assertTrue($installer->install('Gabarito', 700));
        self::assertSame([], $google->takeRequests(), 'a face on disk is never fetched again');
    }

    public function testABundledFaceNeedsNoNetwork(): void
    {
        $google = new GoogleFontsMock();

        self::assertTrue($this->installer($google)->install('Poppins', 700));
        self::assertSame([], $google->takeRequests());
    }

    public function testAFamilyWithoutTheWeightFallsBackToItsDefaultInstance(): void
    {
        $google = new GoogleFontsMock(weightless: ['Anton SC']);

        self::assertTrue($this->installer($google)->install('Anton SC', 700));
        self::assertSame('https://fonts.googleapis.com/css2?family=Anton+SC', $google->takeRequests()[1]);
        self::assertFileExists($this->dir.'/anton-sc-700.ttf');
    }

    public function testAnUnknownFamilyWritesNothing(): void
    {
        $google = new GoogleFontsMock(unknown: ['Nope']);

        self::assertFalse($this->installer($google)->install('Nope', 700));
        self::assertCount(2, $google->takeRequests(), 'weighted, then unweighted');
        self::assertDirectoryDoesNotExist($this->dir);
    }

    public function testInstallPairingFetchesOnlyWellFormedMissingFaces(): void
    {
        $google = new GoogleFontsMock();

        $this->installer($google)->installPairing(new FontPairing('Bad/Name', 'Poppins'));

        self::assertSame([
            'https://fonts.googleapis.com/css2?family=Poppins:wght@400',
            'https://fonts.gstatic.com/s/font/v1/font.ttf',
        ], $google->takeRequests(), 'the malformed heading never reaches Google; the bold bundled Poppins does not serve body text');
        self::assertFileExists($this->dir.'/poppins-400.ttf');

        $this->installer($google)->installPairing(null);
        $this->installer($google)->installPairing('playfair-chivo');
        self::assertSame([], $google->takeRequests(), 'no pairing, or a bundled one, needs no download');
    }
}
