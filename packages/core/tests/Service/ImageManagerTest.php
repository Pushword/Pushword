<?php

namespace Pushword\Core\Tests\Service;

use Pushword\Core\Service\ImageManager;
use Pushword\Core\Tests\PathTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ImageManagerTest extends KernelTestCase
{
    use PathTrait;

    private $imageManager;

    private function getManager(): ImageManager
    {
        if ($this->imageManager) {
            return $this->imageManager;
        }

        return $this->imageManager = new ImageManager([], $this->publicDir, $this->projectDir, $this->publicMediaDir, $this->mediaDir);
    }

    public function testBrowserAndFilterPath()
    {
        $this->assertSame('/'.$this->publicMediaDir.'/default/test.png', $this->getManager()->getBrowserPath('test.png'));
        $this->assertSame('/'.$this->publicMediaDir.'/xs/test.png', $this->getManager()->getBrowserPath('test.png', 'xs'));
        $this->assertSame($this->publicDir.'/'.$this->publicMediaDir.'/default/test.png', $this->getManager()->getFilterPath('test.png', 'default'));
        $this->assertSame($this->publicDir.'/'.$this->publicMediaDir.'/default/test.webp', $this->getManager()->getFilterPath('test.png', 'default', 'webp'));
    }

    public function testFilterCache()
    {
        $image = __DIR__.'/blank.jpg';
        $filters = ['xl' => ['quality' => 80, 'filters' => ['widen' => [1600, 'constraint' => '$constraint->upsize();']]]];
        $this->getManager()->generateFilteredCache($image, $filters);

        $this->assertFileExists($this->publicDir.'/'.$this->publicMediaDir.'/xl/blank.jpg');

        $imgSize = getimagesize($this->publicDir.'/'.$this->publicMediaDir.'/xl/blank.jpg');
        $this->assertSame(1, $imgSize[0]);
        $this->assertSame(1, $imgSize[1]);

        $this->getManager()->remove($image);
        $image = __DIR__.'/blank.jpg';
        $filters = ['xl' => ['quality' => 80, 'filters' => ['widen' => 1600]]];
        $this->getManager()->generateFilteredCache($image, $filters);
        $imgSize = getimagesize($this->publicDir.'/'.$this->publicMediaDir.'/xl/blank.jpg');
        $this->assertSame(1600, $imgSize[0]);

        $this->getManager()->setFilters($filters);
        $this->getManager()->remove($image);
        $this->assertFileDoesNotExist($this->publicDir.'/'.$this->publicMediaDir.'/xl/blank.jpg');
    }

    public function testImportExternal()
    {
        $media = $this->getManager()->importExternal('https://piedweb.com/assets/pw/favicon-32x32.png', 'favicon', 'favicon');
        $this->assertSame('favicon', $media->getName());
        $this->assertSame('favicon-dd93.png', $media->getMedia());
        $this->assertFileExists($this->mediaDir.'/'.$media->getMedia());

        $media = $this->getManager()->importExternal('https://piedweb.com/assets/pw/favicon-32x32.png', 'favicon', 'favicon', false);
        $this->assertSame('favicon.png', $media->getMedia());
        $this->assertFileExists($this->mediaDir.'/'.$media->getMedia());

        $media = $this->getManager()->importExternal('https://piedweb.com/assets/pw/favicon-32x32.png', 'favicon from pied web');
        $this->assertSame('favicon-from-pied-web-dd93.png', $media->getMedia());
        $this->assertFileExists($this->mediaDir.'/'.$media->getMedia());
    }
}
