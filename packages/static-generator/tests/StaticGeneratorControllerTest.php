<?php

namespace Pushword\StaticGenerator\Tests;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\BackgroundTask\BackgroundTaskDispatcherInterface;
use Pushword\Core\Service\BackgroundProcessManager;
use Pushword\Core\Service\ProcessOutputStorage;
use Pushword\StaticGenerator\StaticController;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Twig\Error\RuntimeError;

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

            sleep(1);
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

    public function testDispatchErrorIsShownInConsole(): void
    {
        $client = $this->loginUser();

        $container = $client->getContainer();

        /** @var ProcessOutputStorage $outputStorage */
        $outputStorage = $container->get(ProcessOutputStorage::class);
        $outputStorage->clear('static-generator');

        // Mock process manager to ensure no process appears as running
        $mockProcessManager = self::createStub(BackgroundProcessManager::class);
        $mockProcessManager->method('getProcessInfo')->willReturn(['isRunning' => false, 'startTime' => null, 'pid' => null]);
        $mockProcessManager->method('getPidFilePath')->willReturn(sys_get_temp_dir().'/pushword-test-static.pid');

        $mockDispatcher = self::createStub(BackgroundTaskDispatcherInterface::class);
        $mockDispatcher->method('dispatch')
            ->willThrowException(new RuntimeException('nohup failed'));

        // Test controller directly since compiled container ignores set() for constructor deps
        $mockAdminContext = self::createStub(AdminContextProviderInterface::class);
        $mockAdminContext->method('getContext')->willReturn(null);

        $controller = new StaticController($mockDispatcher, $mockProcessManager, $outputStorage);
        $controller->setAdminContextProvider($mockAdminContext);
        $controller->setContainer($container);

        try {
            $controller->generateStatic();
        } catch (RuntimeError) {
            // Template rendering fails without EasyAdmin context, but error handling already executed
        }

        self::assertSame('error', $outputStorage->getStatus('static-generator'));
        self::assertStringContainsString('nohup failed', $outputStorage->read('static-generator')['content']);
    }
}
