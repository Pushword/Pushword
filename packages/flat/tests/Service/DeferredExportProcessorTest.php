<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests\Service;

use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Flat\Service\DeferredExportProcessor;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\MessageBusInterface;

#[Group('integration')]
final class DeferredExportProcessorTest extends KernelTestCase
{
    private string $tempDir;

    private MessageBusInterface $messageBus;

    #[Override]
    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->messageBus = $kernel->getContainer()->get('messenger.default_bus');

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
        bool $autoExportEnabled = true,
    ): DeferredExportProcessor {
        return new DeferredExportProcessor(
            $this->tempDir,
            $this->messageBus,
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

    public function testProcessQueueWritesPendingFlag(): void
    {
        $processor = $this->createProcessor();
        $page = $this->createPage(1, 'example.com');

        $processor->queue($page, 'update');
        $processor->processQueue();

        self::assertSame([], $processor->getQueue());

        $flag = $processor->readPendingFlag();
        self::assertNotNull($flag);
        self::assertContains('page', $flag['entityTypes']);
        self::assertContains('example.com', $flag['hosts']);
    }

    public function testProcessQueueDoesNothingWhenEmpty(): void
    {
        $processor = $this->createProcessor();
        $processor->processQueue();

        self::assertSame([], $processor->getQueue());
        self::assertNull($processor->readPendingFlag());
    }

    public function testPendingFlagMergesWithExisting(): void
    {
        $processor = $this->createProcessor();

        // First save: page on host-a
        $processor->queue($this->createPage(1, 'host-a.com'), 'update');
        $processor->processQueue();

        // Second save: media on host-b (simulated by creating new processor with same varDir)
        $processor2 = new DeferredExportProcessor($this->tempDir, $this->messageBus);
        $processor2->queue($this->createMedia(2), 'create');
        $processor2->queue($this->createPage(3, 'host-b.com'), 'update');
        $processor2->processQueue();

        $flag = $processor->readPendingFlag();
        self::assertNotNull($flag);
        self::assertContains('page', $flag['entityTypes']);
        self::assertContains('media', $flag['entityTypes']);
        self::assertContains('host-a.com', $flag['hosts']);
        self::assertContains('host-b.com', $flag['hosts']);
    }

    public function testClearPendingFlag(): void
    {
        $processor = $this->createProcessor();
        $processor->queue($this->createPage(1, 'example.com'), 'update');
        $processor->processQueue();

        self::assertNotNull($processor->readPendingFlag());

        $processor->clearPendingFlag();

        self::assertNull($processor->readPendingFlag());
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

    public function testProcessQueueWritesFlagWithCorrectEntityTypesAndHosts(): void
    {
        $processor = $this->createProcessor();

        $processor->queue($this->createPage(1, 'example.com'), 'update');
        $processor->queue($this->createMedia(2), 'create');
        $processor->queue($this->createPage(3, 'other.com'), 'update');
        $processor->processQueue();

        $flag = $processor->readPendingFlag();
        self::assertNotNull($flag);
        self::assertEqualsCanonicalizing(['page', 'media'], $flag['entityTypes']);
        self::assertEqualsCanonicalizing(['example.com', 'other.com'], $flag['hosts']);
    }
}
