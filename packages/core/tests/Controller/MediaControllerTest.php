<?php

namespace Pushword\Core\Tests\Controller;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Controller\MediaController;
use Pushword\Core\Tests\PathTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

#[Group('integration')]
final class MediaControllerTest extends KernelTestCase
{
    use PathTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureMediaFileExists();
    }

    public function testDownload(): void
    {
        self::bootKernel();

        $mediaController = self::getContainer()->get(MediaController::class);
        $response = $mediaController->download('piedweb-logo.png');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testSvgDownloadDisablesActiveContent(): void
    {
        $fileName = 'pushword-security-active.svg';
        $path = $this->getMediaDir().'/'.$fileName;
        new Filesystem()->copy(__DIR__.'/fixtures/active.svg', $path, true);

        try {
            self::bootKernel();
            $response = self::getContainer()->get(MediaController::class)->download($fileName);

            self::assertSame('image/svg+xml', $response->headers->get('Content-Type'));
            self::assertSame(
                "sandbox; default-src 'none'; style-src 'unsafe-inline'; img-src data:",
                $response->headers->get('Content-Security-Policy'),
            );
            self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        } finally {
            new Filesystem()->remove($path);
        }
    }
}
