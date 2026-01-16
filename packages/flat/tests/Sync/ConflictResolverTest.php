<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests\Sync;

use DateTime;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Entity\Page;
use Pushword\Flat\Sync\SyncStateManager;
use ReflectionClass;
use Symfony\Component\Filesystem\Filesystem;

final class ConflictResolverTest extends TestCase
{
    private string $tempDir;

    private string $contentDir;

    private SyncStateManager $stateManager;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/conflict-resolver-test-'.uniqid();
        $this->contentDir = $this->tempDir.'/content';
        mkdir($this->contentDir, 0755, true);

        $this->stateManager = new SyncStateManager($this->tempDir);
    }

    protected function tearDown(): void
    {
        $fs = new Filesystem();
        if (is_dir($this->tempDir)) {
            $fs->remove($this->tempDir);
        }
    }

    public function testNoConflictWhenOnlyFileModified(): void
    {
        $page = $this->createPage();
        $page->updatedAt = new DateTime('-1 hour'); // DB not modified since sync

        $lastSyncAt = new DateTime('-30 minutes');
        $fileModifiedAt = new DateTime('-10 minutes'); // File modified after sync

        // No conflict - only file was modified after sync
        $hasConflict = $this->checkConflict($page, $fileModifiedAt, $lastSyncAt);

        self::assertFalse($hasConflict);
    }

    public function testNoConflictWhenOnlyDbModified(): void
    {
        $page = $this->createPage();
        $page->updatedAt = new DateTime('-10 minutes'); // DB modified after sync

        $lastSyncAt = new DateTime('-30 minutes');
        $fileModifiedAt = new DateTime('-1 hour'); // File not modified since sync

        // No conflict - only DB was modified after sync
        $hasConflict = $this->checkConflict($page, $fileModifiedAt, $lastSyncAt);

        self::assertFalse($hasConflict);
    }

    public function testConflictDetectedWhenBothModified(): void
    {
        $page = $this->createPage();
        $page->updatedAt = new DateTime('-10 minutes'); // DB modified after sync

        $lastSyncAt = new DateTime('-30 minutes');
        $fileModifiedAt = new DateTime('-5 minutes'); // File also modified after sync

        // Conflict - both were modified after sync
        $hasConflict = $this->checkConflict($page, $fileModifiedAt, $lastSyncAt);

        self::assertTrue($hasConflict);
    }

    public function testMostRecentWinsFileNewer(): void
    {
        $page = $this->createPage();
        $page->updatedAt = new DateTime('-10 minutes'); // DB older

        $lastSyncAt = new DateTime('-30 minutes');
        $fileModifiedAt = new DateTime('-5 minutes'); // File is newer

        // File wins
        $winner = $this->getWinner($page, $fileModifiedAt, $lastSyncAt);

        self::assertSame('flat', $winner);
    }

    public function testMostRecentWinsDbNewer(): void
    {
        $page = $this->createPage();
        $page->updatedAt = new DateTime('-5 minutes'); // DB is newer

        $lastSyncAt = new DateTime('-30 minutes');
        $fileModifiedAt = new DateTime('-10 minutes'); // File is older

        // DB wins
        $winner = $this->getWinner($page, $fileModifiedAt, $lastSyncAt);

        self::assertSame('db', $winner);
    }

    public function testRecordConflictStoresData(): void
    {
        $host = 'test.example.com';

        $this->stateManager->recordConflict([
            'entityType' => 'page',
            'entityId' => 1,
            'winner' => 'flat',
            'backupFile' => '/path/to/backup.md',
        ], $host);

        $conflicts = $this->stateManager->getConflicts($host);

        self::assertCount(1, $conflicts);
        self::assertSame('page', $conflicts[0]['entityType']);
        self::assertSame(1, $conflicts[0]['entityId']);
        self::assertSame('flat', $conflicts[0]['winner']);
    }

    /**
     * Simulate conflict detection logic from ConflictResolver.
     */
    private function checkConflict(Page $page, DateTimeInterface $fileModifiedAt, DateTimeInterface $lastSyncAt): bool
    {
        // Both modified since last sync = conflict
        return $fileModifiedAt > $lastSyncAt && $page->updatedAt > $lastSyncAt;
    }

    /**
     * Simulate winner determination logic from ConflictResolver.
     */
    private function getWinner(Page $page, DateTimeInterface $fileModifiedAt, DateTimeInterface $lastSyncAt): ?string
    {
        if (! $this->checkConflict($page, $fileModifiedAt, $lastSyncAt)) {
            return null;
        }

        return $fileModifiedAt >= $page->updatedAt ? 'flat' : 'db';
    }

    private function createPage(): Page
    {
        $page = new Page();
        $page->setSlug('test-page');
        $page->setH1('Test Page');
        $page->host = 'test.host';

        // Use reflection to set the id property
        $reflection = new ReflectionClass($page);
        $property = $reflection->getProperty('id');
        $property->setValue($page, 1);

        return $page;
    }
}
