<?php

namespace Pushword\Core\Tests\Controller;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Controller\MediaController;
use Pushword\Core\Tests\PathTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
class MediaControllerTest extends KernelTestCase
{
    use PathTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureMediaFileExists();
    }

    public function testDownload(): void
    {
        self::bootKernel();

        $mediaController = self::getContainer()->get(MediaController::class);
        $response = $mediaController->download('piedweb-logo.png');
        self::assertSame(200, $response->getStatusCode());
    }
}
