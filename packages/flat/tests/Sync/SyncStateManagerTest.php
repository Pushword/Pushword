<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests\Sync;

use PHPUnit\Framework\TestCase;
use Pushword\Flat\Sync\SyncStateManager;
use Symfony\Component\Filesystem\Filesystem;

final class SyncStateManagerTest extends TestCase
{
    private string $tempDir;

    private SyncStateManager $manager;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/flat-sync-test-'.uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->manager = new SyncStateManager($this->tempDir);
    }

    protected function tearDown(): void
    {
        $fs = new Filesystem();
        if (is_dir($this->tempDir)) {
            $fs->remove($this->tempDir);
        }
    }

    public function testRecordImportUpdatesTimestamp(): void
    {
        $host = 'test.example.com';

        $before = time();
        $this->manager->recordImport('page', $host);
        $after = time();

        $lastSyncTime = $this->manager->getLastSyncTime('page', $host);

        self::assertGreaterThanOrEqual($before, $lastSyncTime);
        self::assertLessThanOrEqual($after, $lastSyncTime);
    }

    public function testRecordExportUpdatesTimestamp(): void
    {
        $host = 'test.example.com';

        $before = time();
        $this->manager->recordExport('media', $host);
        $after = time();

        $lastSyncTime = $this->manager->getLastSyncTime('media', $host);

        self::assertGreaterThanOrEqual($before, $lastSyncTime);
        self::assertLessThanOrEqual($after, $lastSyncTime);
    }

    public function testGetLastSyncTimeReturnsZeroWhenNoState(): void
    {
        $lastSyncTime = $this->manager->getLastSyncTime('page', 'nonexistent.host');

        self::assertSame(0, $lastSyncTime);
    }

    public function testGetLastDirectionReturnsCorrectValue(): void
    {
        $host = 'test.example.com';

        $this->manager->recordImport('page', $host);
        self::assertSame('import', $this->manager->getLastDirection($host));

        $this->manager->recordExport('page', $host);
        self::assertSame('export', $this->manager->getLastDirection($host));
    }

    public function testRecordConflictAppendsToLog(): void
    {
        $host = 'test.example.com';

        $this->manager->recordConflict([
            'entityType' => 'page',
            'entityId' => 42,
            'winner' => 'flat',
        ], $host);

        $conflicts = $this->manager->getConflicts($host);

        self::assertCount(1, $conflicts);
        self::assertSame('page', $conflicts[0]['entityType']);
        self::assertSame(42, $conflicts[0]['entityId']);
        self::assertSame('flat', $conflicts[0]['winner']);
        self::assertArrayHasKey('conflictId', $conflicts[0]);
        self::assertArrayHasKey('conflictDate', $conflicts[0]);
    }

    public function testConflictLogLimitedTo100Entries(): void
    {
        $host = 'test.example.com';

        // Add 105 conflicts
        for ($i = 0; $i < 105; ++$i) {
            $this->manager->recordConflict([
                'entityType' => 'page',
                'entityId' => $i,
                'winner' => 'flat',
            ], $host);
        }

        $conflicts = $this->manager->getConflicts($host);

        self::assertCount(100, $conflicts);
        // The first 5 entries should have been removed, so IDs should start from 5
        self::assertSame(5, $conflicts[0]['entityId']);
    }

    public function testClearConflictsRemovesAllConflicts(): void
    {
        $host = 'test.example.com';

        $this->manager->recordConflict([
            'entityType' => 'page',
            'entityId' => 1,
            'winner' => 'flat',
        ], $host);

        self::assertCount(1, $this->manager->getConflicts($host));

        $this->manager->clearConflicts($host);

        self::assertCount(0, $this->manager->getConflicts($host));
    }

    public function testResetStateRemovesAllState(): void
    {
        $host = 'test.example.com';

        $this->manager->recordImport('page', $host);
        self::assertGreaterThan(0, $this->manager->getLastSyncTime('page', $host));

        $this->manager->resetState($host);

        self::assertSame(0, $this->manager->getLastSyncTime('page', $host));
    }

    public function testNullHostUsesDefault(): void
    {
        $this->manager->recordImport('page', null);
        $lastSyncTime = $this->manager->getLastSyncTime('page', null);

        self::assertGreaterThan(0, $lastSyncTime);
    }
}
