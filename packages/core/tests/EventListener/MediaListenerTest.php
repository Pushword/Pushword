<?php

namespace Pushword\Core\Tests\Controller;

use Exception;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\BackgroundTask\BackgroundTaskDispatcherInterface;
use Pushword\Core\Entity\Media;
use Pushword\Core\Image\ExternalImageImporter;
use Pushword\Core\Image\ImageCacheManager;
use Pushword\Core\Image\ImageEncoder;
use Pushword\Core\Image\ImageReader;
use Pushword\Core\Image\ThumbnailGenerator;
use Pushword\Core\Service\MediaStorageAdapter;
use Pushword\Core\Tests\PathTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MediaListenerTest extends AbstractAdminTestClass // PantherTestCase // KernelTestCase
{
    use PathTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureMediaFileExists();
    }

    public function testRenameMediaOnNameUpdate(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $mediaRepo = $em->getRepository(Media::class);

        $media = $mediaRepo->findOneBy(['fileName' => 'piedweb-logo.png']) ?? throw new Exception();
        $media->setFileName('piedweb.png');

        $em->flush();
        self::assertSame(file_exists($this->getMediaDir().'/piedweb.png'), true);

        $media->setFileName('piedweb-logo.png');
        $em->flush();
    }

    /**
     * // This is not testing MediaListner bug ImageImport (ImageManager Service).
     */
    public function testRenameAndCo(): void
    {
        self::bootKernel();

        $mediaEntity = $this->getImporter()->importExternal(__DIR__.'/media/2.jpg', '1', '', false);
        // $em->persist($mediaEntity);
        self::assertFileExists($this->getMediaDir().'/1-2.jpg');

        // If import twice, return the existing one and not create a new copy
        $mediaEntity = $this->getImporter()->importExternal(__DIR__.'/media/2.jpg', '1', '', false);
        self::assertFileDoesNotExist($this->getMediaDir().'/1-3.jpg');
        self::assertSame($mediaEntity->getFileName(), '1-2.jpg');
        unlink($this->getMediaDir().'/1-2.jpg');
        self::assertFileDoesNotExist($this->getMediaDir().'/1-2.jpg');
    }

    // 1. A new image is properly renamed when another image with the same name already exists (no overwrite)
    public function testRenameNewMediaIfAnotherMediaHasSameName(): void
    {
        $files = [
            __DIR__.'/media/2.jpg',
            __DIR__.'/media/2',
            // __DIR__.'/media/2.withoutMimeType.jpg', //=> this will create 1
        ];

        foreach ($files as $file) {
            $client = $this->loginUser();
            $client->catchExceptions(false);
            $crawler = $this->requestMediaCreateForm($client);
            $fileInput = $crawler->filter('[type="file"]');
            $formId = strtok($fileInput->getNode(0)->getAttribute('name'), '['); // @phpstan-ignore-line
            $form = $crawler->filter('form[method="post"]')->form([
                $formId.'[mediaFile]' => $file,
            ]);
            $client->submit($form);
            self::assertSame(Response::HTTP_FOUND, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
            self::assertFileExists($this->getMediaDir().'/2-2.jpg');

            $crawler = $this->requestMediaCreateForm($client);
            $fileInput = $crawler->filter('[type="file"]');
            $formId = strtok($fileInput->getNode(0)->getAttribute('name'), '['); // @phpstan-ignore-line
            $form = $crawler->filter('form[method="post"]')->form([
                $formId.'[mediaFile]' => $file,
                $formId.'[alt]' => '1',
            ]);

            $client->submit($form);
            self::assertSame(Response::HTTP_FOUND, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
            self::assertFileExists($this->getMediaDir().'/1-2.jpg');

            $crawler = $this->requestMediaCreateForm($client);
            $fileInput = $crawler->filter('[type="file"]');
            $formId = strtok($fileInput->getNode(0)->getAttribute('name'), '['); // @phpstan-ignore-line
            $form = $crawler->filter('form[method="post"]')->form([
                $formId.'[mediaFile]' => $file,
                $formId.'[slugForce]' => '1',
            ]);

            $client->submit($form);
            self::assertSame(Response::HTTP_FOUND, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
            self::assertFileExists($this->getMediaDir().'/1-3.jpg');

            $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

            $mediaRepo = $em->getRepository(Media::class);

            $medias = $mediaRepo->findBy([], ['id' => 'DESC'], 3, 0);
            foreach ($medias as $m) {
                $em->remove($m);
            }

            $em->flush();
            self::assertFileDoesNotExist($this->getMediaDir().'/1-4.jpg');
            self::assertFileDoesNotExist($this->getMediaDir().'/1-3.jpg');
        }
    }

    // Todo
    // 1. When I change a slug, the file is properly renamed
    // 2. When I replace a media, it keeps the same file path
    // 3. When I change a name, only the name is modified

    private ?ExternalImageImporter $importer = null;

    private function getImporter(): ExternalImageImporter
    {
        if (null !== $this->importer) {
            return $this->importer;
        }

        /** @var MediaStorageAdapter $mediaStorage */
        $mediaStorage = self::getContainer()->get(MediaStorageAdapter::class);
        $imageReader = new ImageReader($mediaStorage);
        $imageEncoder = new ImageEncoder();
        $imageCacheManager = new ImageCacheManager([], $this->publicDir, $this->publicMediaDir, $mediaStorage);
        $backgroundTaskDispatcher = self::getContainer()->get(BackgroundTaskDispatcherInterface::class);
        $thumbnailGenerator = new ThumbnailGenerator($imageReader, $imageEncoder, $imageCacheManager, $backgroundTaskDispatcher, $mediaStorage);

        return $this->importer = new ExternalImageImporter($mediaStorage, $thumbnailGenerator, $this->getMediaDir(), $this->projectDir);
    }

    private function requestMediaCreateForm(KernelBrowser $client): Crawler
    {
        $createUrl = $this->generateAdminUrl('admin_media_create');

        return $client->request(Request::METHOD_GET, $createUrl);
    }
}
