<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests\Service;

use Override;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\BackgroundProcessManager;
use Pushword\Flat\Service\DeferredExportProcessor;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

final class DeferredExportProcessorTest extends KernelTestCase
{
    private BackgroundProcessManager $processManager;

    private string $tempDir;

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();

        /** @var BackgroundProcessManager $pm */
        $pm = self::getContainer()->get(BackgroundProcessManager::class);
        $this->processManager = $pm;

        $this->tempDir = sys_get_temp_dir().'/deferred-export-test-'.uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    #[Override]
    protected function tearDown(): void
    {
        $fs = new Filesystem();
        if (is_dir($this->tempDir)) {
            $fs->remove($this->tempDir);
        }

        parent::tearDown();
    }

    private function createProcessor(
        bool $useBackgroundProcess = false,
        bool $autoExportEnabled = true,
    ): DeferredExportProcessor {
        return new DeferredExportProcessor(
            $this->processManager,
            $this->tempDir,
            $useBackgroundProcess,
            $autoExportEnabled,
        );
    }

    private function createPage(int $id, ?string $host = null): Page
    {
        $page = new Page();
        $reflection = new ReflectionClass($page);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setValue($page, $id);

        $page->host = $host;

        return $page;
    }

    private function createMedia(int $id): Media
    {
        $media = new Media();
        $reflection = new ReflectionClass($media);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setValue($media, $id);

        return $media;
    }

    public function testQueueAddsEntityToQueue(): void
    {
        $processor = $this->createProcessor();
        $page = $this->createPage(1, 'example.com');

        $processor->queue($page, 'update');

        $queue = $processor->getQueue();

        self::assertCount(1, $queue);
        self::assertArrayHasKey('page_1', $queue);
        self::assertSame('page', $queue['page_1']['type']);
        self::assertSame(1, $queue['page_1']['id']);
        self::assertSame('example.com', $queue['page_1']['host']);
        self::assertSame('update', $queue['page_1']['action']);
    }

    public function testQueueDeduplicatesByEntityKey(): void
    {
        $processor = $this->createProcessor();
        $page = $this->createPage(1, 'example.com');

        $processor->queue($page, 'create');
        $processor->queue($page, 'update');

        $queue = $processor->getQueue();

        self::assertCount(1, $queue);
        self::assertSame('update', $queue['page_1']['action']);
    }

    public function testQueueHandlesMediaEntities(): void
    {
        $processor = $this->createProcessor();
        $media = $this->createMedia(5);

        $processor->queue($media, 'create');

        $queue = $processor->getQueue();

        self::assertCount(1, $queue);
        self::assertArrayHasKey('media_5', $queue);
        self::assertSame('media', $queue['media_5']['type']);
        self::assertSame(5, $queue['media_5']['id']);
        self::assertNull($queue['media_5']['host']);
    }

    public function testQueueDoesNothingWhenAutoExportDisabled(): void
    {
        $processor = $this->createProcessor(autoExportEnabled: false);
        $page = $this->createPage(1, 'example.com');

        $processor->queue($page, 'update');

        self::assertSame([], $processor->getQueue());
    }

    public function testProcessQueueClearsQueue(): void
    {
        $processor = $this->createProcessor(useBackgroundProcess: false);
        $page = $this->createPage(1, 'example.com');

        $processor->queue($page, 'update');
        self::assertCount(1, $processor->getQueue());

        $processor->processQueue();

        self::assertSame([], $processor->getQueue());
    }

    public function testProcessQueueDoesNothingWhenEmpty(): void
    {
        $processor = $this->createProcessor();
        $processor->processQueue();

        self::assertSame([], $processor->getQueue());
    }

    public function testQueueMultipleEntityTypes(): void
    {
        $processor = $this->createProcessor();

        $processor->queue($this->createPage(1, 'example.com'), 'update');
        $processor->queue($this->createMedia(2), 'create');

        $queue = $processor->getQueue();

        self::assertCount(2, $queue);
        self::assertArrayHasKey('page_1', $queue);
        self::assertArrayHasKey('media_2', $queue);
    }

    public function testIsEnabledReturnsTrueWhenEnabled(): void
    {
        $processor = $this->createProcessor(autoExportEnabled: true);

        self::assertTrue($processor->isEnabled());
    }

    public function testIsEnabledReturnsFalseWhenDisabled(): void
    {
        $processor = $this->createProcessor(autoExportEnabled: false);

        self::assertFalse($processor->isEnabled());
    }

    public function testQueueHandlesNewEntityWithoutId(): void
    {
        $processor = $this->createProcessor();
        $page = new Page();
        $page->host = 'example.com';

        $processor->queue($page, 'create');

        $queue = $processor->getQueue();

        self::assertCount(1, $queue);
        self::assertArrayHasKey('page_new', $queue);
        self::assertNull($queue['page_new']['id']);
    }

    public function testQueueMultipleHosts(): void
    {
        $processor = $this->createProcessor();

        $processor->queue($this->createPage(1, 'host-a.com'), 'update');
        $processor->queue($this->createPage(2, 'host-b.com'), 'update');

        $queue = $processor->getQueue();

        self::assertCount(2, $queue);
        self::assertSame('host-a.com', $queue['page_1']['host']);
        self::assertSame('host-b.com', $queue['page_2']['host']);
    }

    public function testProcessQueueWithInlineExport(): void
    {
        $processor = $this->createProcessor(useBackgroundProcess: false);
        $page = $this->createPage(1, 'example.com');

        $processor->queue($page, 'update');

        $processor->processQueue();

        self::assertSame([], $processor->getQueue());
    }
}
