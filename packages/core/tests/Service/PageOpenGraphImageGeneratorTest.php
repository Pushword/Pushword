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
final class PageOpenGraphImageGeneratorTest extends KernelTestCase
{
    private function buildGenerator(?LoggerInterface $logger = null, ?string $mediaCacheDir = null): PageOpenGraphImageGenerator
    {
        $siteRegistry = self::getContainer()->get(SiteRegistry::class);

        // These tests exercise generation itself, so they have to ask for it: the test
        // env ships `generated_og_image` off (config/packages/test/og_image.yaml), which
        // otherwise makes generatePreviewImage() return before doing anything.
        $siteRegistry->get()->setCustomProperty('generated_og_image', true);

        return new PageOpenGraphImageGenerator(
            $siteRegistry,
            self::getContainer()->get('twig'),
            new Filesystem(),
            'media',
            $mediaCacheDir ?? sys_get_temp_dir().'/media',
            logger: $logger,
        );
    }

    /**
     * The one place the real Imagick path runs.
     *
     * Every other test here replaces the Imagine driver, and the listener tests mock the
     * generator outright, so without this nothing would ever draw an actual image: fonts,
     * logo lookup and save would break silently. It used to be covered only by accident,
     * by the ~845 renders a suite run triggered through page saves.
     */
    public function testItDrawsAndSavesARealPng(): void
    {
        self::bootKernel();

        $mediaCacheDir = sys_get_temp_dir().'/pushword-og-render-'.getmypid();
        $filesystem = new Filesystem();
        $filesystem->remove($mediaCacheDir);

        $generator = $this->buildGenerator(mediaCacheDir: $mediaCacheDir);

        $page = new Page();
        $page->slug = 'og-real-render';
        $page->h1 = 'A heading long enough that the drawer has to wrap it across lines';

        $generator->setPage($page)->generatePreviewImage();

        $path = $generator->getPath();
        self::assertFileExists($path);

        $size = getimagesize($path);
        self::assertNotFalse($size);
        self::assertSame([1200, 600, \IMAGETYPE_PNG], [$size[0], $size[1], $size[2]]);

        $filesystem->remove($mediaCacheDir);
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

    public function testResetClearsLeakedPageForWorkerMode(): void
    {
        self::bootKernel();

        $generator = $this->buildGenerator();

        $first = new Page();
        $first->slug = 'first-request';

        $generator->setPage($first);
        self::assertSame($first, $generator->getPage());

        // Simulate the kernel.reset between two worker requests: the previous
        // request's page must not leak into the next one.
        $generator->reset();
        self::assertNull($generator->page);

        $second = new Page();
        $second->slug = 'second-request';

        $generator->setPage($second);
        self::assertSame($second, $generator->getPage());
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
        $page->slug = 'test-page';

        $generator->setPage($page)->generatePreviewImage();
    }
}
