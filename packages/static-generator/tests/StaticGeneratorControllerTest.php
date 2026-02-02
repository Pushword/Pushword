<?php

namespace Pushword\StaticGenerator\Tests;

use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Service\BackgroundProcessManager;
use Pushword\Core\Service\ProcessOutputStorage;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;

#[Group('integration')]
class StaticGeneratorControllerTest extends AbstractAdminTestClass
{
    #[Override]
    protected function tearDown(): void
    {
        // Wait for any background static generation process to complete
        // to avoid interfering with subsequent tests
        $processManager = self::getContainer()->get(BackgroundProcessManager::class);
        $outputStorage = self::getContainer()->get(ProcessOutputStorage::class);
        $pidFile = $processManager->getPidFilePath('static-generator');

        // Wait up to 30 seconds for the process to complete
        $maxWait = 30;
        $waited = 0;
        while ($waited < $maxWait) {
            $processManager->cleanupStaleProcess($pidFile);
            $info = $processManager->getProcessInfo($pidFile);
            if (! $info['isRunning']) {
                break;
            }

            usleep(100000); // 100ms - poll frequently instead of sleeping 1s
            ++$waited;
        }

        // Clean up PID file and output storage
        new Filesystem()->remove($pidFile);
        $outputStorage->clear('static-generator');

        parent::tearDown();
    }

    public function testController(): void
    {
        $client = $this->loginUser();

        $client->catchExceptions(false);

        $client->request(Request::METHOD_GET, '/admin/static');
        self::assertResponseIsSuccessful();

        $client->request(Request::METHOD_GET, '/admin/static/localhost.dev');
        self::assertResponseIsSuccessful();
    }
}
