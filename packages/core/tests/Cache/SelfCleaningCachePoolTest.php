<?php

namespace Pushword\Core\Tests\Cache;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Cache\SelfCleaningCachePool;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Filesystem\Filesystem;

final class SelfCleaningCachePoolTest extends TestCase
{
    private string $maintenanceDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->maintenanceDir = sys_get_temp_dir().'/pw-markdown-maintenance-'.uniqid();
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->maintenanceDir);

        parent::tearDown();
    }

    public function testFirstAccessClearsLegacyImmortalEntriesOnlyOnce(): void
    {
        $inner = $this->recordingPool();
        $item = $inner->getItem('legacy');
        $item->set('immortal');

        $inner->save($item);

        $pool = new SelfCleaningCachePool($inner, $this->maintenanceDir);

        self::assertFalse($pool->getItem('legacy')->isHit());
        self::assertSame(1, $inner->clearCalls);

        new SelfCleaningCachePool($inner, $this->maintenanceDir)->getItem('another-process');
        self::assertSame(1, $inner->clearCalls);
    }

    public function testAccessPrunesOnceWhenDailyMaintenanceIsDue(): void
    {
        new Filesystem()->dumpFile($this->maintenanceDir.'/markdown-maintenance', '1');
        touch($this->maintenanceDir.'/markdown-maintenance', time() - 86401);
        $inner = $this->recordingPool();
        $pool = new SelfCleaningCachePool($inner, $this->maintenanceDir);

        $pool->getItem('first');
        $pool->getItem('second');

        self::assertSame(0, $inner->clearCalls);
        self::assertSame(1, $inner->pruneCalls);
    }

    public function testRecentMarkerFromAnOlderMaintenanceVersionClearsLegacyEntries(): void
    {
        new Filesystem()->dumpFile($this->maintenanceDir.'/markdown-maintenance', '0');
        $inner = $this->recordingPool();
        $item = $inner->getItem('legacy');
        $item->set('immortal');

        $inner->save($item);

        $pool = new SelfCleaningCachePool($inner, $this->maintenanceDir);

        self::assertFalse($pool->getItem('legacy')->isHit());
        self::assertSame(1, $inner->clearCalls);
    }

    public function testMaintenanceFailureDoesNotBreakCacheAccess(): void
    {
        $inner = $this->recordingPool(throwOnClear: true);
        $item = $inner->getItem('available');
        $item->set('value');

        $inner->save($item);

        $pool = new SelfCleaningCachePool($inner, $this->maintenanceDir);

        self::assertSame('value', $pool->getItem('available')->get());
    }

    public function testResetIsDelegatedToTheDecoratedPool(): void
    {
        $inner = $this->recordingPool();

        new SelfCleaningCachePool($inner, $this->maintenanceDir)->reset();

        self::assertSame(1, $inner->resetCalls);
    }

    /** @return ArrayAdapter&PruneableInterface&object{clearCalls: int, pruneCalls: int, resetCalls: int} */
    private function recordingPool(bool $throwOnClear = false): ArrayAdapter&PruneableInterface
    {
        return new class($throwOnClear) extends ArrayAdapter implements PruneableInterface {
            public int $clearCalls = 0;

            public int $pruneCalls = 0;

            public int $resetCalls = 0;

            public function __construct(private readonly bool $throwOnClear)
            {
                parent::__construct();
            }

            public function clear(string $prefix = ''): bool
            {
                ++$this->clearCalls;
                if ($this->throwOnClear) {
                    throw new RuntimeException('maintenance failed');
                }

                return parent::clear($prefix);
            }

            public function prune(): bool
            {
                ++$this->pruneCalls;

                return true;
            }

            public function reset(): void
            {
                ++$this->resetCalls;
                parent::reset();
            }
        };
    }
}
