<?php

namespace Pushword\Core\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\Group;
use ReflectionProperty;

use function Safe\realpath;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\TraceableAdapter;

#[Group('integration')]
final class MarkdownCachePoolTest extends KernelTestCase
{
    /**
     * The pool must live NEXT TO kernel.cache_dir, not inside it: cache:clear
     * (hence every deploy) wipes the cache dir, and the deterministic
     * content-keyed markdown/TOC fragments are exactly the cache that should
     * survive it.
     */
    public function testMarkdownPoolLivesOutsideTheCacheDirSoCacheClearSparesIt(): void
    {
        self::bootKernel();
        $pool = self::getContainer()->get('cache.pushword_markdown');

        $adapter = $pool;
        while ($adapter instanceof TraceableAdapter) {
            $adapter = $adapter->getPool();
        }

        self::assertInstanceOf(FilesystemAdapter::class, $adapter);
        $directory = new ReflectionProperty(FilesystemAdapter::class, 'directory')->getValue($adapter);
        self::assertIsString($directory);

        /** @var string $cacheDir */
        $cacheDir = self::getContainer()->getParameter('kernel.cache_dir');

        self::assertStringStartsNotWith(realpath($cacheDir).'/', $directory);
        self::assertStringStartsWith(\dirname(realpath($cacheDir)).'/pushword-pools/', $directory);

        $item = $pool->getItem('pool_location_smoke');
        $item->set('ok');

        $pool->save($item);
        self::assertSame('ok', $pool->getItem('pool_location_smoke')->get());
        $pool->deleteItem('pool_location_smoke');
    }
}
