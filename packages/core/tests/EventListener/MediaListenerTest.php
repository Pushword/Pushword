<?php

namespace Pushword\Core\Tests\Controller;

use App\Entity\Media;
use Pushword\Core\Repository\Repository;
use Pushword\Core\Service\ImageManager;
use Pushword\Core\Tests\PathTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class MediaListenerTest extends KernelTestCase
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

    // A tester
    // 1. Si une nouvelle image se renomme bien dans le cas d'une image existante avec le même nom (pas d'écrasement)
        // Ok via Admin (VichUploader listener)
    // 1.2 ... avec une image sans extension
        // OK viaAdmin (VichUploader listener)
    // 1.3 ... avec une image avec extension mais sans MimeType
        // Ok viaAdmin (VichUploader listener)
    // Idem 3 précédent mais sur un edit d'image
        // OK bloquer par UniqueEntity

    /*
    public function testRenameAndCo()
    {
        self::bootKernel();
        $em = self::$kernel->getContainer()->get('doctrine.orm.default_entity_manager');

        $mediaEntity = $this->getImageManager()->importExternal(__DIR__.'/media/2', '1', '', false);
        $em->persist($mediaEntity);
        $em->flush();
    }


    private $imageManager;

    private function getImageManager(): ImageManager
    {
        if ($this->imageManager) {
            return $this->imageManager;
        }

        return $this->imageManager = new ImageManager([], $this->publicDir, $this->projectDir, $this->publicMediaDir, $this->mediaDir);
    }*/
}
