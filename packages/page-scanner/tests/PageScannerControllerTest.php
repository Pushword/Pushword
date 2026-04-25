<?php

declare(strict_types=1);

namespace Pushword\TemplateEditor\Tests;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\BackgroundTask\BackgroundTaskDispatcherInterface;
use Pushword\Core\Service\BackgroundProcessManager;
use Pushword\Core\Service\ProcessOutputStorage;
use Pushword\Core\Site\SiteRegistry;
use Pushword\PageScanner\Controller\PageScannerController;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Twig\Error\RuntimeError;

#[Group('integration')]
final class PageScannerControllerTest extends AbstractAdminTestClass
{
    public function testAdmin(): void
    {
        $client = $this->loginUser();

        $client->catchExceptions(false);

        $client->request(Request::METHOD_GET, '/admin/scan');
        self::assertResponseIsSuccessful();
    }

    public function testAdminWithHost(): void
    {
        $client = $this->loginUser();

        $client->catchExceptions(false);

        $client->request(Request::METHOD_GET, '/admin/scan?host=localhost.dev');
        self::assertResponseIsSuccessful();
    }

    public function testDispatchErrorIsShownInConsole(): void
    {
        $client = $this->loginUser();
        $container = $client->getContainer();

        $isolatedDir = sys_get_temp_dir().'/pw-test-'.uniqid();
        $outputStorage = new ProcessOutputStorage(new Filesystem(), $isolatedDir);

        $mockProcessManager = self::createStub(BackgroundProcessManager::class);
        $mockProcessManager->method('getProcessInfo')->willReturn(['isRunning' => false, 'startTime' => null, 'pid' => null]);
        $mockProcessManager->method('getPidFilePath')->willReturn(sys_get_temp_dir().'/pushword-test-scanner.pid');

        $mockDispatcher = self::createStub(BackgroundTaskDispatcherInterface::class);
        $mockDispatcher->method('dispatch')
            ->willThrowException(new RuntimeException('nohup failed'));

        $mockAdminContext = self::createStub(AdminContextProviderInterface::class);
        $mockAdminContext->method('getContext')->willReturn(null);

        /** @var SiteRegistry $siteRegistry */
        $siteRegistry = $container->get(SiteRegistry::class);

        $controller = new PageScannerController(
            new Filesystem(),
            $isolatedDir,
            'PT5M',
            $mockDispatcher,
            $mockProcessManager,
            $outputStorage,
            $siteRegistry,
        );
        $controller->setAdminContextProvider($mockAdminContext);
        $controller->setContainer($container);

        try {
            $controller->scan(new Request(['host' => '']), 1);
        } catch (RuntimeError) {
            // Template rendering fails without EasyAdmin context, but error handling already executed
        }

        self::assertSame('error', $outputStorage->getStatus('page-scanner'));
        self::assertStringContainsString('nohup failed', $outputStorage->read('page-scanner')['content']);

        new Filesystem()->remove($isolatedDir);
    }

    public function testDispatchErrorWithHostUsesPerHostProcessType(): void
    {
        $client = $this->loginUser();
        $container = $client->getContainer();

        $isolatedDir = sys_get_temp_dir().'/pw-test-'.uniqid();
        $outputStorage = new ProcessOutputStorage(new Filesystem(), $isolatedDir);

        $mockProcessManager = self::createStub(BackgroundProcessManager::class);
        $mockProcessManager->method('getProcessInfo')->willReturn(['isRunning' => false, 'startTime' => null, 'pid' => null]);
        $mockProcessManager->method('getPidFilePath')->willReturn(sys_get_temp_dir().'/pushword-test-scanner-host.pid');

        $mockDispatcher = self::createStub(BackgroundTaskDispatcherInterface::class);
        $mockDispatcher->method('dispatch')
            ->willThrowException(new RuntimeException('nohup failed'));

        $mockAdminContext = self::createStub(AdminContextProviderInterface::class);
        $mockAdminContext->method('getContext')->willReturn(null);

        /** @var SiteRegistry $siteRegistry */
        $siteRegistry = $container->get(SiteRegistry::class);

        $controller = new PageScannerController(
            new Filesystem(),
            $isolatedDir,
            'PT5M',
            $mockDispatcher,
            $mockProcessManager,
            $outputStorage,
            $siteRegistry,
        );
        $controller->setAdminContextProvider($mockAdminContext);
        $controller->setContainer($container);

        try {
            $controller->scan(new Request(['host' => 'localhost.dev']), 1);
        } catch (RuntimeError) {
        }

        self::assertSame('error', $outputStorage->getStatus('page-scanner--localhost.dev'));
        self::assertStringContainsString('nohup failed', $outputStorage->read('page-scanner--localhost.dev')['content']);

        new Filesystem()->remove($isolatedDir);
    }
}
