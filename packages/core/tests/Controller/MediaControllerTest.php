<?php

namespace Pushword\Core\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class MediaControllerTest extends KernelTestCase
{
    public function testDownload()
    {
        self::bootKernel();

        $mediaController = self::$kernel->getContainer()->get('Pushword\Core\Controller\MediaController');
        $response = $mediaController->download('piedweb-logo.png');
        $this->assertTrue(200 === $response->getStatusCode());
    }
}
