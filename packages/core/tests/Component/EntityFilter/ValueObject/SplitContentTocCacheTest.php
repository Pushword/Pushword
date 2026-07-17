<?php

namespace Pushword\Core\Tests\Component\EntityFilter\ValueObject;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Component\EntityFilter\ValueObject\SplitContent;
use Pushword\Core\Entity\Page;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\CacheItem;

/**
 * The heading-fix + TOC-extraction step is cached by content hash: a hit must
 * reproduce exactly what a fresh computation yields.
 */
final class SplitContentTocCacheTest extends TestCase
{
    public function testCachedTocResultMatchesFreshComputation(): void
    {
        $html = '<p>my intro</p><h2>First Title</h2><p>text</p><h3>Child</h3><p>more</p>'
            .'<!--stop-toc--><h2>Hidden Title</h2><p>end</p>';

        $page = new Page();
        $page->host = 'localhost';
        $page->setCustomProperty('toc', true);

        $fresh = new SplitContent($html, $page);
        $pool = new ArrayAdapter();
        $miss = new SplitContent($html, $page, $pool);
        self::assertNotSame([], $pool->getValues(), 'the computed result must be stored in the pool');
        $hit = new SplitContent($html, $page, $pool);

        foreach (['miss' => $miss, 'hit' => $hit] as $label => $cached) {
            self::assertSame($fresh->getBody(), $cached->getBody(), $label);
            self::assertSame($fresh->getIntro(), $cached->getIntro(), $label);
            self::assertSame($fresh->getToc(), $cached->getToc(), $label);
        }

        $toc = $hit->getToc();
        self::assertIsString($toc);
        self::assertStringContainsString('#first-title', $toc);
        self::assertStringNotContainsString('#hidden-title', $toc);
        self::assertStringContainsString('id="hidden-title"', $hit->getBody());
    }

    public function testBrokenCacheBackendNeverBreaksRendering(): void
    {
        $page = new Page();
        $page->host = 'localhost';
        $page->setCustomProperty('toc', true);

        $brokenPool = new class extends ArrayAdapter {
            public function getItem(mixed $key): CacheItem
            {
                throw new RuntimeException('backend down');
            }
        };

        $splitContent = new SplitContent('<h2>My Title</h2><p>text</p>', $page, $brokenPool);

        $toc = $splitContent->getToc();
        self::assertIsString($toc);
        self::assertStringContainsString('#my-title', $toc);
        self::assertStringContainsString('id="my-title"', $splitContent->getBody());
    }
}
