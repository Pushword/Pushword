<?php

namespace Pushword\Core\Tests\Twig;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

#[Group('integration')]
class ImageTemplateTest extends KernelTestCase
{
    private function getTwig(): Environment
    {
        self::bootKernel();

        /** @var Environment */
        return self::getContainer()->get('twig');
    }

    private function createMedia(int $width = 1200, int $height = 800): Media
    {
        $media = new Media();
        $media->setFileName('test-image.jpg');
        $media->setAlt('Test image');
        $media->imageData->setDimensions([$width, $height]);

        return $media;
    }

    public function testResponsiveModeGeneratesMultipleSrcset(): void
    {
        $twig = $this->getTwig();
        $html = $twig->render('@PushwordCore/component/image.html.twig', [
            'image' => $this->createMedia(),
        ]);

        // Responsive mode (default) should generate multiple breakpoints
        self::assertStringContainsString('<picture', $html);
        self::assertStringContainsString('576w', $html);
        self::assertStringContainsString('768w', $html);
        self::assertStringContainsString('992w', $html);
        self::assertStringContainsString('width="1200"', $html);
        self::assertStringContainsString('height="800"', $html);
    }

    public function testSingleFilterModeUsesActualDimensions(): void
    {
        $twig = $this->getTwig();
        $html = $twig->render('@PushwordCore/component/image.html.twig', [
            'image' => $this->createMedia(900, 600),
            'mode' => 'xs',
            'page' => new Page(),
        ]);

        // Should use actual image dimensions, not hardcoded 1000x1000
        self::assertStringContainsString('width="900"', $html);
        self::assertStringContainsString('height="600"', $html);
        self::assertStringNotContainsString('width="1000"', $html);
    }

    public function testNoThumbModeHardcodedDimensions(): void
    {
        $twig = $this->getTwig();

        // Even if someone passes mode='thumb' (with custom config), dimensions should come from the image
        $html = $twig->render('@PushwordCore/component/image.html.twig', [
            'image' => $this->createMedia(500, 400),
            'mode' => 'md',
            'page' => new Page(),
        ]);

        self::assertStringContainsString('width="500"', $html);
        self::assertStringContainsString('height="400"', $html);
        self::assertStringNotContainsString('1000', $html);
    }
}
