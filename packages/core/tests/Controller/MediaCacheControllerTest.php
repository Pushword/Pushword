<?php

namespace Pushword\Core\Tests\Controller;

use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Controller\MediaCacheController;
use Pushword\Core\Image\ImageCacheGenerator;
use Pushword\Core\Image\ImageCacheManager;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Service\MediaCacheStorageAdapter;
use Pushword\Core\Service\MediaStorageAdapter;
use Pushword\Core\Tests\PathTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Group('integration')]
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

    public function testRegeneratesEmptyVariantInsteadOfServingIt(): void
    {
        $cacheManager = self::getContainer()->get(ImageCacheManager::class);
        $webpPath = $cacheManager->getFilterPath('piedweb-logo.png', 'md', 'webp');

        // A poisoned cache entry: a 0-byte variant left by a failed encode/optimize.
        $filesystem = new Filesystem();
        $filesystem->mkdir(\dirname($webpPath));
        $filesystem->dumpFile($webpPath, '');

        $this->client->request(Request::METHOD_GET, '/media/md/piedweb-logo.webp');

        // It must be rebuilt and served with real bytes, never as HTTP 200 / Content-Length: 0.
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, (int) filesize($webpPath), 'The empty variant must be regenerated, not served');
    }

    public function testRemoteVariantRedirectsToConfiguredPublicUrl(): void
    {
        $storage = new Flysystem(new InMemoryFilesystemAdapter());
        $storage->write('md/piedweb-logo.webp', 'remote-variant');

        $controller = $this->createRemoteController($storage, 'https://media.example.test/cache');

        $response = $controller->generate('md', 'piedweb-logo.webp');

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('https://media.example.test/cache/md/piedweb-logo.webp', $response->getTargetUrl());
    }

    public function testRemoteVariantIsStreamedWithoutPublicUrl(): void
    {
        $storage = new Flysystem(new InMemoryFilesystemAdapter());
        $storage->write('md/piedweb-logo.webp', 'remote-variant');

        $controller = $this->createRemoteController($storage);

        $response = $controller->generate('md', 'piedweb-logo.webp');

        self::assertInstanceOf(StreamedResponse::class, $response);
        ob_start();
        $response->sendContent();
        self::assertSame('remote-variant', ob_get_clean());
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

    private function createRemoteController(Flysystem $storage, string $publicUrl = ''): MediaCacheController
    {
        $remoteCache = new MediaCacheStorageAdapter($storage, isLocal: false, publicUrl: $publicUrl);
        $cacheManager = new ImageCacheManager(
            ['md' => ['formats' => ['webp']]],
            $this->publicMediaDir,
            $this->getMediaCacheDir(),
            self::getContainer()->get(MediaStorageAdapter::class),
            mediaCacheStorage: $remoteCache,
        );

        return new MediaCacheController(
            self::getContainer()->get(MediaRepository::class),
            $cacheManager,
            self::getContainer()->get(ImageCacheGenerator::class),
            $remoteCache,
        );
    }
}
