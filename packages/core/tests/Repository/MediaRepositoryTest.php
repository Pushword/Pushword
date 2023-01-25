<?php

namespace Pushword\Core\Tests\Controller;

use App\Entity\Media;
use Pushword\Core\Entity\MediaInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class MediaRepositoryTest extends KernelTestCase
{
    public function testFindDuplicate()
    {
        self::bootKernel();

        $em = self::$kernel->getContainer()->get('doctrine.orm.default_entity_manager');
        $duplicate = $em->getRepository('App\Entity\Media')->findDuplicate((new Media())->setHash('testFakeHash'));
        $this->assertNull($duplicate);

        $duplicate = $em->getRepository('App\Entity\Media')->findDuplicate($this->getMediaToTestDuplicate());
        $this->assertInstanceOf(MediaInterface::class, $duplicate);
    }

    public function getMediaToTestDuplicate()
    {
        return (new Media())->setProjectDir($this->getContainer()->getParameter('kernel.project_dir'))
            ->setStoreIn($this->getContainer()->getParameter('pw.media_dir'))
            ->setMimeType('image/jpg')
            ->setSize(2)
            ->setDimensions([1000, 1000])
            ->setMedia('1.jpg')
            ->setName('Demo 1')
            ->setHash();
    }
}
