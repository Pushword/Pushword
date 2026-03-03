<?php

namespace Pushword\TemplateEditor\Tests;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\BackgroundTask\BackgroundTaskDispatcherInterface;
use Pushword\Core\Service\ProcessOutputStorage;
use RuntimeException;
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

        /** @var ProcessOutputStorage $outputStorage */
        $outputStorage = $client->getContainer()->get(ProcessOutputStorage::class);
        $outputStorage->clear('page-scanner');

        // force=1 to bypass the interval check and actually dispatch
        $client->request(Request::METHOD_GET, '/admin/scan/1');
        self::assertResponseIsSuccessful();

        self::assertSame('error', $outputStorage->getStatus('page-scanner'));
        self::assertStringContainsString('nohup failed', $outputStorage->read('page-scanner')['content']);
    }
}
