<?php

namespace Pushword\Core\Tests\Controller;

use Pushword\Admin\Tests\AbstractAdminTest;
use Pushword\Core\Repository\Repository;
use Pushword\Core\Service\ImageManager;
use Pushword\Core\Tests\PathTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Panther\PantherTestCase;

class MediaListenerTest extends AbstractAdminTest // PantherTestCase // KernelTestCase
{
    use PathTrait;

    public function testRenameMediaOnNameUpdate()
    {
        self::bootKernel();

        $em = self::$kernel->getContainer()->get('doctrine.orm.default_entity_manager');
        $media = Repository::getMediaRepository($em, 'App\Entity\Media')->findOneBy(['media' => 'piedweb-logo.png']);
        $media->setMedia('piedweb.png');
        $em->flush();
        $this->assertSame(file_exists($this->mediaDir.'/piedweb.png'), true);

        $media->setMedia('piedweb-logo.png');
        $em->flush();
    }

    /**
     * // This is not testing MediaListner bug ImageImport (ImageManager Service).
     */
    public function testRenameAndCo()
    {
        self::bootKernel();
        $em = self::$kernel->getContainer()->get('doctrine.orm.default_entity_manager');

        $mediaEntity = $this->getImageManager()->importExternal(__DIR__.'/media/2.jpg', '1', '', false);
        // $em->persist($mediaEntity);
        $this->assertFileExists($this->mediaDir.'/1-2.jpg');

        // If import twice, return the existing one and not create a new copy
        $mediaEntity = $this->getImageManager()->importExternal(__DIR__.'/media/2.jpg', '1', '', false);
        $this->assertFileDoesNotExist($this->mediaDir.'/1-3.jpg');
        $this->assertSame($mediaEntity->getMedia(), '1-2.jpg');
        unlink(__DIR__.'/../../../skeleton/media/1-2.jpg');
        $this->assertFileDoesNotExist($this->mediaDir.'/1-2.jpg');
    }

    // 1. Si une nouvelle image se renomme bien dans le cas d'une image existante avec le même nom (pas d'écrasement)
    public function testRenameNewMediaIfAnotherMediaHasSameName()
    {
        $files = [
            __DIR__.'/media/2.jpg',
            __DIR__.'/media/2',
            // __DIR__.'/media/2.withoutMimeType.jpg', //=> this will create 1
        ];

        foreach ($files as $file) {
            // dump($file);
            $client = $this->loginUser();
            $client->catchExceptions(false);
            $crawler = $client->request('GET', '/admin/app/media/create');
            $formId = strtok($crawler->filter('[type="file"]')->getNode(0)->getAttribute('name'), '[');
            $form = $crawler->filter('[role="form"]')->form([
                $formId.'[mediaFile]' => $file,
            ]);
            $client->submit($form);
            $this->assertEquals(302, $client->getResponse()->getStatusCode());
            $this->assertFileExists($this->mediaDir.'/2-2.jpg');

            $crawler = $client->request('GET', '/admin/app/media/create');
            $formId = strtok($crawler->filter('[type="file"]')->getNode(0)->getAttribute('name'), '[');
            $form = $crawler->filter('[role="form"]')->form([
                $formId.'[mediaFile]' => $file,
                $formId.'[name]' => '1',
            ]);

            $client->submit($form);
            $this->assertEquals(302, $client->getResponse()->getStatusCode());
            $this->assertFileExists($this->mediaDir.'/1-2.jpg');

            $crawler = $client->request('GET', '/admin/app/media/create');
            $formId = strtok($crawler->filter('[type="file"]')->getNode(0)->getAttribute('name'), '[');
            $form = $crawler->filter('[role="form"]')->form([
                $formId.'[mediaFile]' => $file,
                $formId.'[slugForce]' => '1',
            ]);

            $client->submit($form);
            $this->assertEquals(302, $client->getResponse()->getStatusCode());
            $this->assertFileExists($this->mediaDir.'/1-3.jpg');

            $em = self::$kernel->getContainer()->get('doctrine.orm.default_entity_manager');
            $medias = Repository::getMediaRepository($em, 'App\Entity\Media')->findBy([], ['id' => 'DESC'], 3, 0);
            foreach ($medias as $m) {
                $em->remove($m);
            }
            $em->flush();
            $this->assertFileDoesNotExist($this->mediaDir.'/1-4.jpg');
            $this->assertFileDoesNotExist($this->mediaDir.'/1-3.jpg');
        }
    }

    // Todo
    // 1. Quand je modifie un slug, le fichier est bien modifié
    // 2. Quand je remplace un media, le media garde le même chemin d'accès
    // 3. Quand je modifie un nom, seul le nom est modifié

    private ?ImageManager $imageManager = null;

    private function getImageManager(): ImageManager
    {
        if ($this->imageManager) {
            return $this->imageManager;
        }

        return $this->imageManager = new ImageManager([], $this->publicDir, $this->projectDir, $this->publicMediaDir, $this->mediaDir);
    }
}
