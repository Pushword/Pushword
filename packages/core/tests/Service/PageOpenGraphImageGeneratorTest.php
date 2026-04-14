<?php

namespace Pushword\Core\Tests\Service;

use Imagine\Image\ImagineInterface;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\PageOpenGraphImageGenerator;
use Pushword\Core\Site\SiteRegistry;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

#[Group('integration')]
class PageOpenGraphImageGeneratorTest extends KernelTestCase
{
    private function buildGenerator(?LoggerInterface $logger = null): PageOpenGraphImageGenerator
    {
        return new PageOpenGraphImageGenerator(
            self::getContainer()->get(SiteRegistry::class),
            self::getContainer()->get('twig'),
            new Filesystem(),
            sys_get_temp_dir(),
            'media',
            logger: $logger,
        );
    }

    public function testSkipsWhenPageHasMainImage(): void
    {
        self::bootKernel();

        $generator = $this->buildGenerator();

        $media = self::createStub(Media::class);

        $page = new Page();
        $page->mainImage = $media;

        $imagine = self::createMock(ImagineInterface::class);
        $imagine->expects(self::never())->method('create');
        $generator->imagine = $imagine;

        $generator->setPage($page)->generatePreviewImage();
    }

    public function testLogsErrorAndDoesNotThrowWhenImagickFails(): void
    {
        self::bootKernel();

        $logger = self::createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(
            self::stringContains('OG image generation failed'),
            self::arrayHasKey('slug'),
        );

        $generator = $this->buildGenerator($logger);

        $imagine = self::createStub(ImagineInterface::class);
        $imagine->method('create')->willThrowException(new RuntimeException('Could not create empty image'));
        $generator->imagine = $imagine;

        $page = new Page();
        $page->setSlug('test-page');

        $generator->setPage($page)->generatePreviewImage();
    }
}
