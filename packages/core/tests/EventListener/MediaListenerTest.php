<?php

namespace Pushword\Core\Tests\Controller;

use Pushword\Core\Repository\Repository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class MediaListenerTest extends KernelTestCase
{
    public function testRenameMediaOnNameUpdate()
    {
        self::bootKernel();

        $em = self::$kernel->getContainer()->get('doctrine.orm.default_entity_manager');
        $media = Repository::getMediaRepository($em, 'App\Entity\Media')->findOneBy(['media' => 'piedweb-logo.png']);
        $media->setMedia('piedweb.png');
        $em->flush();
        $this->assertSame(file_exists(__DIR__.'/../../../skeleton/media/piedweb.png'), true);

        $media->setMedia('piedweb-logo.png');
        $em->flush();
    }
}
