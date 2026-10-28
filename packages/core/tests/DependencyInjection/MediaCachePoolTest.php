<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\EventListener\MediaCacheInvalidationListener;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Service\Markdown\MarkdownParser;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\TraceableAdapter;

#[Group('integration')]
final class MediaCachePoolTest extends KernelTestCase
{
    public function testMediaReadersAndWritersUseTheSameFilesystemPool(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $pool = $container->get('cache.pushword_media');
        self::assertNotSame($container->get('cache.app'), $pool);
        $adapter = $pool;
        while ($adapter instanceof TraceableAdapter) {
            $adapter = $adapter->getPool();
        }

        self::assertInstanceOf(FilesystemAdapter::class, $adapter);
        foreach ([MediaRepository::class => 'cache', MediaCacheInvalidationListener::class => 'cache', MarkdownParser::class => 'versionCache'] as $class => $property) {
            self::assertSame($pool, new ReflectionProperty($class, $property)->getValue($container->get($class)));
        }
    }
}
