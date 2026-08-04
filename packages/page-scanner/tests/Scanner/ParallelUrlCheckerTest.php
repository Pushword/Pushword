<?php

namespace Pushword\PageScanner\Tests\Scanner;

use PHPUnit\Framework\Attributes\Group;
use Pushword\PageScanner\Scanner\ParallelUrlChecker;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class ParallelUrlCheckerTest extends KernelTestCase
{
    /**
     * `.invalid` is reserved as never-resolving (RFC 2606), so this is the one live
     * failure needing no network and no server: an unreachable host by definition,
     * whatever the machine running the suite can reach.
     *
     * Regression: curl_multi leaves `curl_errno()` at 0 — the transfer error lives in
     * `curl_multi_info_read()` — so every unresolvable host used to read as reachable.
     */
    public function testAHostThatCannotResolveIsReportedAsUnreachable(): void
    {
        self::bootKernel();
        $url = 'https://pushword-does-not-resolve.invalid/x';
        $cache = self::getContainer()->get('cache.page_scanner');
        $cache->deleteItem(ParallelUrlChecker::cacheKey($url));

        try {
            /** @var ParallelUrlChecker $checker */
            $checker = self::getContainer()->get(ParallelUrlChecker::class);
            $result = $checker->checkUrls([$url]);

            self::assertIsArray($result[$url], 'An unresolvable host is a finding, not a pass.');
            self::assertSame('link-unreachable', $result[$url]['code']);
            self::assertNotSame('', $result[$url]['message']);
        } finally {
            $cache->deleteItem(ParallelUrlChecker::cacheKey($url));
        }
    }

    /**
     * Regression: the miss-probe used to save its own null, so the pool answered every
     * later get() — the storing one included — with it, and nothing was ever cached.
     */
    public function testTheFindingIsCachedUnderTheSharedKey(): void
    {
        self::bootKernel();
        $url = 'https://pushword-cached.invalid/x';
        $cache = self::getContainer()->get('cache.page_scanner');
        $cache->deleteItem(ParallelUrlChecker::cacheKey($url));

        try {
            /** @var ParallelUrlChecker $checker */
            $checker = self::getContainer()->get(ParallelUrlChecker::class);
            $checker->checkUrls([$url]);

            $item = $cache->getItem(ParallelUrlChecker::cacheKey($url));

            self::assertTrue($item->isHit(), 'A checked URL must not have to be checked again.');
            $cached = $item->get();
            self::assertIsArray($cached);
            self::assertSame('link-unreachable', $cached['code']);
        } finally {
            $cache->deleteItem(ParallelUrlChecker::cacheKey($url));
        }
    }
}
