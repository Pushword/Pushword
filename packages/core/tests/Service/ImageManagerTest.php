<?php

namespace Pushword\Core\Tests\Service;

use Pushword\Core\Service\ImageManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ImageManagerTest extends KernelTestCase
{
    public function testIt()
    {
        $publicDir = __DIR__.'/../../../skeleton/public';
        $publicMediaDir = '/media';

        $manager = new ImageManager([], $publicDir, $publicMediaDir);

        $this->assertSame($publicMediaDir.'/default/test.png', $manager->getBrowserPath('test.png'));
        $this->assertSame($publicMediaDir.'/xs/test.png', $manager->getBrowserPath('test.png', 'xs'));
        $this->assertSame($publicDir.$publicMediaDir.'/default/test.png', $manager->getFilterPath('test.png', 'default'));
        $this->assertSame($publicDir.$publicMediaDir.'/default/test.webp', $manager->getFilterPath('test.png', 'default', 'webp'));

        $image = __DIR__.'/blank.jpg';
        $filters = ['xl' => ['quality' => 80, 'filters' => ['widen' => [1600, 'constraint' => '$constraint->upsize();']]]];
        $manager->generateFilteredCache($image, $filters);

        $this->assertFileExists($publicDir.$publicMediaDir.'/xl/blank.jpg');

        $imgSize = getimagesize($publicDir.$publicMediaDir.'/xl/blank.jpg');
        $this->assertSame(1, $imgSize[0]);
        $this->assertSame(1, $imgSize[1]);

        $manager->remove($image);
        $image = __DIR__.'/blank.jpg';
        $filters = ['xl' => ['quality' => 80, 'filters' => ['widen' => 1600]]];
        $manager->generateFilteredCache($image, $filters);
        $imgSize = getimagesize($publicDir.$publicMediaDir.'/xl/blank.jpg');
        $this->assertSame(1600, $imgSize[0]);

        $manager->setFilters($filters);
        $manager->remove($image);
        $this->assertFileDoesNotExist($publicDir.$publicMediaDir.'/xl/blank.jpg');
    }
}
