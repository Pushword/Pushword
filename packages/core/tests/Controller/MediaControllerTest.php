<?php

namespace Pushword\Core\Tests\Controller;

use Pushword\Core\Controller\MediaController;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class MediaControllerTest extends KernelTestCase
{
    public function testDownload(): void
    {
        self::bootKernel();

        $mediaController = self::getContainer()->get(MediaController::class);
        $response = $mediaController->download('piedweb-logo.png');
        self::assertSame(200, $response->getStatusCode());
    }
}
