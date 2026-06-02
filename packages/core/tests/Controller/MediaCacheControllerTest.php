<?php

namespace Pushword\Core\Tests\Controller;

use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Image\ImageCacheManager;
use Pushword\Core\Tests\PathTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;

// Serial: these tests unlink/regenerate variants of the shared piedweb-logo fixture
// in public/media (a dir shared across all paratest workers), so they need exclusive
// access — otherwise a concurrent worker mutating the same path flakes them.
#[Group('integration')]
#[Group('serial')]
final class MediaCacheControllerTest extends WebTestCase
{
    use PathTrait;

    private KernelBrowser $client;

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->ensureMediaFileExists();
    }

    public function testGeneratesMissingVariantOnTheFly(): void
    {
        $cacheManager = self::getContainer()->get(ImageCacheManager::class);
        $webpPath = $cacheManager->getFilterPath('piedweb-logo.png', 'md', 'webp');
        @unlink($webpPath);

        $this->client->request(Request::METHOD_GET, '/media/md/piedweb-logo.webp');

        self::assertResponseIsSuccessful();
        self::assertFileExists($webpPath);
    }

    public function testGeneratesOriginalFormatVariantOnTheFly(): void
    {
        $cacheManager = self::getContainer()->get(ImageCacheManager::class);
        $originalPath = $cacheManager->getFilterPath('piedweb-logo.png', 'md');
        @unlink($originalPath);

        // The original-format variant resolves the media by exact name (not the webp
        // fallback) and the 'md' filter keeps an `original` format alongside webp.
        $this->client->request(Request::METHOD_GET, '/media/md/piedweb-logo.png');

        self::assertResponseIsSuccessful();
        self::assertFileExists($originalPath);
    }

    public function testServesAlreadyGeneratedVariantWithoutRegenerating(): void
    {
        $cacheManager = self::getContainer()->get(ImageCacheManager::class);
        $webpPath = $cacheManager->getFilterPath('piedweb-logo.png', 'md', 'webp');

        $filesystem = new Filesystem();
        $filesystem->mkdir(\dirname($webpPath));
        $filesystem->dumpFile($webpPath, 'SENTINEL');

        $this->client->request(Request::METHOD_GET, '/media/md/piedweb-logo.webp');

        self::assertResponseIsSuccessful();
        // Existing variant is served as-is; the controller must not regenerate it.
        self::assertSame('SENTINEL', file_get_contents($webpPath));

        // Drop the sentinel so the real variant is rebuilt on the next access.
        @unlink($webpPath);
    }

    public function testUnknownFilterReturns404(): void
    {
        $this->client->request(Request::METHOD_GET, '/media/notafilter/piedweb-logo.webp');
        self::assertResponseStatusCodeSame(404);
    }

    public function testUnknownMediaReturns404(): void
    {
        $this->client->request(Request::METHOD_GET, '/media/md/definitely-missing-xyz.webp');
        self::assertResponseStatusCodeSame(404);
    }
}
