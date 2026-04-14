<?php

namespace Pushword\Conversation\Tests\Flat;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Conversation\Flat\ConversationSync;
use Pushword\Flat\Sync\ConversationSyncInterface;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class ConversationSyncCachingTest extends KernelTestCase
{
    public function testGlobalMustImportCacheReusedAcrossHosts(): void
    {
        self::bootKernel();

        /** @var ConversationSync $conversationSync */
        $conversationSync = self::getContainer()->get(ConversationSyncInterface::class);

        // First call populates the cache
        $resultHost1 = $conversationSync->mustImport('localhost.dev');

        $ref = new ReflectionProperty(ConversationSync::class, 'globalMustImportCache');
        $cacheAfterFirst = $ref->getValue($conversationSync);

        self::assertNotNull($cacheAfterFirst, 'globalMustImportCache should be set after first call in global mode');
        self::assertSame($resultHost1, $cacheAfterFirst);

        // Second call for different host should return cached value
        $resultHost2 = $conversationSync->mustImport('pushword.piedweb.com');

        self::assertSame($resultHost1, $resultHost2, 'In global mode, mustImport should return the same cached result for all hosts');

        // Cache should still be the same value
        $cacheAfterSecond = $ref->getValue($conversationSync);
        self::assertSame($cacheAfterFirst, $cacheAfterSecond, 'Cache should not change between calls');
    }

    public function testGlobalExportRunsOnceAcrossHosts(): void
    {
        self::bootKernel();

        /** @var ConversationSync $conversationSync */
        $conversationSync = self::getContainer()->get(ConversationSyncInterface::class);

        $ref = new ReflectionProperty(ConversationSync::class, 'globalExported');

        self::assertFalse($ref->getValue($conversationSync), 'globalExported should be false initially');

        $conversationSync->export('localhost.dev');
        self::assertTrue($ref->getValue($conversationSync), 'globalExported should be true after first export');

        // Second export for different host is a no-op (flag already set)
        $conversationSync->export('pushword.piedweb.com');
        self::assertTrue($ref->getValue($conversationSync), 'globalExported should remain true');
    }

    public function testGlobalImportRunsOnceAcrossHosts(): void
    {
        self::bootKernel();

        /** @var ConversationSync $conversationSync */
        $conversationSync = self::getContainer()->get(ConversationSyncInterface::class);

        $ref = new ReflectionProperty(ConversationSync::class, 'globalImported');

        self::assertFalse($ref->getValue($conversationSync), 'globalImported should be false initially');

        $conversationSync->import('localhost.dev');
        self::assertTrue($ref->getValue($conversationSync), 'globalImported should be true after first import');

        // Second import for different host is a no-op (flag already set)
        $conversationSync->import('pushword.piedweb.com');
        self::assertTrue($ref->getValue($conversationSync), 'globalImported should remain true');
    }
}
