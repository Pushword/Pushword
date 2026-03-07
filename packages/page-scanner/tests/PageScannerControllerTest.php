<?php

namespace Pushword\TemplateEditor\Tests;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\BackgroundTask\BackgroundTaskDispatcherInterface;
use Pushword\Core\Service\ProcessOutputStorage;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;

#[Group('integration')]
class PageScannerControllerTest extends AbstractAdminTestClass
{
    public function testAdmin(): void
    {
        $client = $this->loginUser();

        $client->catchExceptions(false);

        $client->request(Request::METHOD_GET, '/admin/scan');
        self::assertResponseIsSuccessful();
    }

    public function testDispatchErrorIsShownInConsole(): void
    {
        $client = $this->loginUser();

        $client->disableReboot();

        $mockDispatcher = self::createStub(BackgroundTaskDispatcherInterface::class);
        $mockDispatcher->method('dispatch')
            ->willThrowException(new RuntimeException('nohup failed'));
        $client->getContainer()->set(BackgroundTaskDispatcherInterface::class, $mockDispatcher);

        // Use an isolated temp directory to avoid race conditions with parallel tests
        $isolatedDir = sys_get_temp_dir().'/pw-test-'.uniqid();
        $isolatedStorage = new ProcessOutputStorage(new Filesystem(), $isolatedDir);
        $client->getContainer()->set(ProcessOutputStorage::class, $isolatedStorage);

        // force=1 to bypass the interval check and actually dispatch
        $client->request(Request::METHOD_GET, '/admin/scan/1');
        self::assertResponseIsSuccessful();

        self::assertSame('error', $isolatedStorage->getStatus('page-scanner'));
        self::assertStringContainsString('nohup failed', $isolatedStorage->read('page-scanner')['content']);

        (new Filesystem())->remove($isolatedDir);
    }
}
