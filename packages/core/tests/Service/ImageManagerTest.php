<?php

namespace Pushword\Core\Tests\Service;

use Pushword\Core\Service\ImageManager;
use Pushword\Core\Tests\PathTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ImageManagerTest extends KernelTestCase
{
    use PathTrait;

    private ?ImageManager $imageManager = null;

    private function getManager(): ImageManager
    {
        if (null !== $this->imageManager) {
            return $this->imageManager;
        }

        return $this->imageManager = new ImageManager([], $this->publicDir, $this->projectDir, $this->publicMediaDir, $this->mediaDir);
    }

    public function testBrowserAndFilterPath(): void
    {
        self::assertSame('/'.$this->publicMediaDir.'/default/test.png', $this->getManager()->getBrowserPath('test.png'));
        self::assertSame('/'.$this->publicMediaDir.'/xs/test.png', $this->getManager()->getBrowserPath('test.png', 'xs'));
        self::assertSame($this->publicDir.'/'.$this->publicMediaDir.'/default/test.png', $this->getManager()->getFilterPath('test.png', 'default'));
        self::assertSame($this->publicDir.'/'.$this->publicMediaDir.'/default/test.webp', $this->getManager()->getFilterPath('test.png', 'default', 'webp'));
    }

    public function testFilterCache(): void
    {
        $image = __DIR__.'/blank.jpg';
        $filters = ['xl' => ['quality' => 80, 'filters' => ['scaleDown' => [1600]]]];
        $this->getManager()->generateFilteredCache($image, $filters);

        self::assertFileExists($this->publicDir.'/'.$this->publicMediaDir.'/xl/blank.jpg');

        $imgSize = getimagesize($this->publicDir.'/'.$this->publicMediaDir.'/xl/blank.jpg');
        self::assertIsArray($imgSize);
        self::assertSame(1, $imgSize[0]);
        self::assertSame(1, $imgSize[1]);

        $this->getManager()->remove($image);
        $image = __DIR__.'/blank.jpg';
        $filters = ['xl' => ['quality' => 80, 'filters' => ['scale' => 1600]]];
        $this->getManager()->generateFilteredCache($image, $filters);
        $imgSize = getimagesize($this->publicDir.'/'.$this->publicMediaDir.'/xl/blank.jpg');
        self::assertIsArray($imgSize);
        self::assertSame(1600, $imgSize[0]);

        $this->getManager()->setFilters($filters);
        $this->getManager()->remove($image);
        self::assertFileDoesNotExist($this->publicDir.'/'.$this->publicMediaDir.'/xl/blank.jpg');
    }

    public function testImportExternal(): void
    {
        $media = $this->getManager()->importExternal('https://piedweb.com/assets/pw/favicon-32x32.png', 'favicon', 'favicon');
        self::assertSame('favicon', $media->getName());
        self::assertSame('favicon-dd93.png', $media->getMedia());
        self::assertFileExists($this->mediaDir.'/'.$media->getMedia());

        $media = $this->getManager()->importExternal('https://piedweb.com/assets/pw/favicon-32x32.png', 'favicon', 'favicon', false);
        self::assertSame('favicon.png', $media->getMedia());
        self::assertFileExists($this->mediaDir.'/'.$media->getMedia());

        $media = $this->getManager()->importExternal('https://piedweb.com/assets/pw/favicon-32x32.png', 'favicon from pied web');
        self::assertSame('favicon-from-pied-web-dd93.png', $media->getMedia());
        self::assertFileExists($this->mediaDir.'/'.$media->getMedia());
    }
}
