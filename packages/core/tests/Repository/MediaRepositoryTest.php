<?php

namespace Pushword\Core\Tests\Controller;

use Pushword\Core\Entity\Media;
use Pushword\Core\Repository\MediaRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class MediaRepositoryTest extends KernelTestCase
{
    public function testFindDuplicate(): void
    {
        $repo = $this->getMediaRepository();

        $duplicate = $repo->findDuplicate(new Media()->setHash('testFakeHash'));
        self::assertNull($duplicate);

        $duplicate = $repo->findDuplicate($this->getMediaToTestDuplicate());
        self::assertInstanceOf(Media::class, $duplicate);
    }

    public function testFindOneBySearchMatchesFileName(): void
    {
        $repo = $this->getMediaRepository();

        $result = $repo->findOneBySearch('1.jpg');

        self::assertNotNull($result);
        self::assertSame('1.jpg', $result->getFileName());
    }

    public function testFindOneBySearchMatchesAlt(): void
    {
        $repo = $this->getMediaRepository();

        $result = $repo->findOneBySearch('Demo 1');

        self::assertNotNull($result);
        self::assertStringContainsString('Demo', $result->getAlt());
    }

    public function testFindOneBySearchMatchesPartialFileName(): void
    {
        $repo = $this->getMediaRepository();

        $result = $repo->findOneBySearch('piedweb-logo');

        self::assertNotNull($result);
        self::assertSame('piedweb-logo.png', $result->getFileName());
    }

    public function testFindOneBySearchReturnsNullForNoMatch(): void
    {
        $repo = $this->getMediaRepository();

        $result = $repo->findOneBySearch('zzz_nonexistent_file_xyz');

        self::assertNull($result);
    }

    public function testFindOneBySearchFileNamePriorityOverAlt(): void
    {
        $repo = $this->getMediaRepository();

        // 'logo' appears in fileName 'piedweb-logo.png' and 'logo.svg'
        // The method should return a result matching via fileName first
        $result = $repo->findOneBySearch('logo');

        self::assertNotNull($result);
        self::assertStringContainsString('logo', $result->getFileName());
    }

    private function getMediaRepository(): MediaRepository
    {
        self::bootKernel();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        return $em->getRepository(Media::class);
    }

    public function getMediaToTestDuplicate(): Media
    {
        return new Media()->setProjectDir(self::getContainer()->getParameter('kernel.project_dir'))
            ->setStoreIn(self::getContainer()->getParameter('pw.media_dir'))
            ->setMimeType('image/jpeg')
            ->setSize(2)
            ->setDimensions([1000, 1000])
            ->setFileName('1.jpg')
            ->setAlt('Demo 1')
            ->setHash();
    }
}
