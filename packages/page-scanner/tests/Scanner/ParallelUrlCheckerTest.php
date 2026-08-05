<?php

namespace Pushword\PageScanner\Tests\Scanner;

use PHPUnit\Framework\Attributes\Group;
use Pushword\PageScanner\Scanner\ParallelUrlChecker;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\CacheItem;

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

    /**
     * A finding is held for less time than a pass: a link fixed this morning has to
     * stop being reported this morning, and one scan run while the network is down
     * would otherwise report every external URL of the corpus as dead for a day.
     */
    public function testAFindingIsHeldForLessTimeThanAPass(): void
    {
        $finding = ['code' => 'link-unreachable', 'message' => 'x'];

        self::assertSame(86400, ParallelUrlChecker::ttlFor(true, 86400, 3600));
        self::assertSame(3600, ParallelUrlChecker::ttlFor($finding, 86400, 3600));

        // Turning the cache off has to turn it off for findings too, whatever the
        // failure TTL says.
        self::assertSame(0, ParallelUrlChecker::ttlFor($finding, 0, 3600));
    }

    /**
     * And the rule is the one the write applies — `ttlFor()` agreeing with itself
     * says nothing about the entry that actually lands in the pool.
     */
    public function testTheStoredFindingCarriesTheShorterTtl(): void
    {
        self::bootKernel();
        $url = 'https://pushword-ttl.invalid/x';
        $cache = self::getContainer()->get('cache.page_scanner');
        $cache->deleteItem(ParallelUrlChecker::cacheKey($url));

        try {
            /** @var ParallelUrlChecker $checker */
            $checker = self::getContainer()->get(ParallelUrlChecker::class);
            $checker->checkUrls([$url]);

            $expiry = $cache->getItem(ParallelUrlChecker::cacheKey($url))->getMetadata()[CacheItem::METADATA_EXPIRY];
            self::assertIsInt($expiry);
            $ttl = $expiry - time();

            self::assertGreaterThan(0, $ttl);
            self::assertLessThanOrEqual(3600, $ttl, 'A finding must not be held for the success TTL.');
        } finally {
            $cache->deleteItem(ParallelUrlChecker::cacheKey($url));
        }
    }

    /**
     * `--recheck` is the only way out of a poisoned entry: the verdict is written
     * through get(), which keeps what the pool already holds, so the stale one has
     * to be dropped before the check rather than overwritten after it.
     */
    public function testRecheckAsksTheNetworkAgainAndReplacesTheCachedVerdict(): void
    {
        self::bootKernel();
        $url = 'https://pushword-recheck.invalid/x';
        $cache = self::getContainer()->get('cache.page_scanner');
        $cache->deleteItem(ParallelUrlChecker::cacheKey($url));
        $cache->get(ParallelUrlChecker::cacheKey($url), static fn (): true => true);

        try {
            /** @var ParallelUrlChecker $checker */
            $checker = self::getContainer()->get(ParallelUrlChecker::class);

            self::assertTrue($checker->checkUrls([$url])[$url], 'Without --recheck the cached verdict stands.');

            $result = $checker->checkUrls([$url], true);
            self::assertIsArray($result[$url], 'An unresolvable host is a finding, whatever the pool held.');

            $cached = $cache->getItem(ParallelUrlChecker::cacheKey($url))->get();
            self::assertIsArray($cached, 'The stale entry has to be gone, not just bypassed.');
        } finally {
            $cache->deleteItem(ParallelUrlChecker::cacheKey($url));
        }
    }
}
