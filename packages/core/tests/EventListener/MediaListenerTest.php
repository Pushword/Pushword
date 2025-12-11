<?php

namespace Pushword\Core\Tests\Controller;

use Exception;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Entity\Media;
use Pushword\Core\Service\ImageManager;
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
        self::assertSame(file_exists($this->mediaDir.'/piedweb.png'), true);

        $media->setFileName('piedweb-logo.png');
        $em->flush();
    }

    /**
     * // This is not testing MediaListner bug ImageImport (ImageManager Service).
     */
    public function testRenameAndCo(): void
    {
        self::bootKernel();

        $mediaEntity = $this->getImageManager()->importExternal(__DIR__.'/media/2.jpg', '1', '', false);
        // $em->persist($mediaEntity);
        self::assertFileExists($this->mediaDir.'/1-2.jpg');

        // If import twice, return the existing one and not create a new copy
        $mediaEntity = $this->getImageManager()->importExternal(__DIR__.'/media/2.jpg', '1', '', false);
        self::assertFileDoesNotExist($this->mediaDir.'/1-3.jpg');
        self::assertSame($mediaEntity->getFileName(), '1-2.jpg');
        unlink(__DIR__.'/../../../skeleton/media/1-2.jpg');
        self::assertFileDoesNotExist($this->mediaDir.'/1-2.jpg');
    }

    // 1. Si une nouvelle image se renomme bien dans le cas d'une image existante avec le même nom (pas d'écrasement)
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
            self::assertFileExists($this->mediaDir.'/2-2.jpg');

            $crawler = $this->requestMediaCreateForm($client);
            $fileInput = $crawler->filter('[type="file"]');
            $formId = strtok($fileInput->getNode(0)->getAttribute('name'), '['); // @phpstan-ignore-line
            $form = $crawler->filter('form[method="post"]')->form([
                $formId.'[mediaFile]' => $file,
                $formId.'[alt]' => '1',
            ]);

            $client->submit($form);
            self::assertSame(Response::HTTP_FOUND, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
            self::assertFileExists($this->mediaDir.'/1-2.jpg');

            $crawler = $this->requestMediaCreateForm($client);
            $fileInput = $crawler->filter('[type="file"]');
            $formId = strtok($fileInput->getNode(0)->getAttribute('name'), '['); // @phpstan-ignore-line
            $form = $crawler->filter('form[method="post"]')->form([
                $formId.'[mediaFile]' => $file,
                $formId.'[slugForce]' => '1',
            ]);

            $client->submit($form);
            self::assertSame(Response::HTTP_FOUND, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
            self::assertFileExists($this->mediaDir.'/1-3.jpg');

            $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

            $mediaRepo = $em->getRepository(Media::class);

            $medias = $mediaRepo->findBy([], ['id' => 'DESC'], 3, 0);
            foreach ($medias as $m) {
                $em->remove($m);
            }

            $em->flush();
            self::assertFileDoesNotExist($this->mediaDir.'/1-4.jpg');
            self::assertFileDoesNotExist($this->mediaDir.'/1-3.jpg');
        }
    }

    // Todo
    // 1. Quand je modifie un slug, le fichier est bien modifié
    // 2. Quand je remplace un media, le media garde le même chemin d'accès
    // 3. Quand je modifie un nom, seul le nom est modifié

    private ?ImageManager $imageManager = null;

    private function getImageManager(): ImageManager
    {
        if (null !== $this->imageManager) {
            return $this->imageManager;
        }

        /** @var \Pushword\Core\Service\MediaStorageAdapter $mediaStorage */
        $mediaStorage = self::getContainer()->get(\Pushword\Core\Service\MediaStorageAdapter::class);

        return $this->imageManager = new ImageManager([], $this->publicDir, $this->projectDir, $this->publicMediaDir, $this->mediaDir, $mediaStorage);
    }

    private function requestMediaCreateForm(KernelBrowser $client): Crawler
    {
        $createUrl = $this->generateAdminUrl('admin_media_create');

        return $client->request(Request::METHOD_GET, $createUrl);
    }
}
