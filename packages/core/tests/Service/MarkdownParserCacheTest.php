<?php

namespace Pushword\Core\Tests\Service;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Service\LinkProvider;
use Pushword\Core\Service\Markdown\MarkdownParser;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Twig\MediaExtension;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\CacheItem;

/**
 * Guards the markdown render cache: it must be transparent (identical output)
 * and actually serve from cache on a hit.
 */
#[Group('integration')]
final class MarkdownParserCacheTest extends KernelTestCase
{
    private function buildParser(ArrayAdapter $pool, int $mediaVersion = 0): MarkdownParser
    {
        self::bootKernel();
        $container = self::getContainer();

        $versionCache = new ArrayAdapter();
        if (0 !== $mediaVersion) {
            $item = $versionCache->getItem(MediaRepository::VERSION_CACHE_KEY);
            $item->set($mediaVersion);
            $versionCache->save($item);
        }

        return new MarkdownParser(
            $container->get(LinkProvider::class),
            $container->get(MediaExtension::class),
            $container->get(SiteRegistry::class),
            $pool,
            $versionCache,
        );
    }

    public function testOutputIsIdenticalWithCache(): void
    {
        $pool = new ArrayAdapter();
        $parser = $this->buildParser($pool);

        $markdown = "# Title\n\nSome **bold** text with a [link](/page) and a list:\n\n- one\n- two";

        $first = $parser->transform($markdown);
        $second = $parser->transform($markdown);

        self::assertSame($first, $second);
        self::assertStringContainsString('<strong>bold</strong>', $first);
        self::assertNotSame([], $pool->getValues(), 'a fragment should have been cached');
    }

    public function testServesFromCacheOnHit(): void
    {
        $pool = new ArrayAdapter();
        $parser = $this->buildParser($pool);

        $markdown = '# Cached Heading';

        // Prime the cache, then poison the stored value: a cache hit must return it.
        $parser->transform($markdown);
        // Image-free fragment: bare parser version, no media token.
        $key = 'pw_md.'.hash('xxh3', '2|'.$markdown);
        $item = $pool->getItem($key);
        self::assertTrue($item->isHit(), 'fragment should be cached under the expected key');
        $item->set('POISONED');
        $pool->save($item);

        self::assertSame('POISONED', $parser->transform($markdown));
    }

    public function testImageFreeFragmentSurvivesMediaVersionBump(): void
    {
        $pool = new ArrayAdapter();
        $markdown = '# Plain heading with no media at all';

        // Prime under media version 0.
        $this->buildParser($pool, 0)->transform($markdown);

        // A media write bumped the version. The image-free fragment must NOT be
        // re-keyed: a parser seeing version 7 still serves the primed entry.
        $key = 'pw_md.'.hash('xxh3', '2|'.$markdown);
        $item = $pool->getItem($key);
        self::assertTrue($item->isHit(), 'image-free fragment is keyed without the media version');
        $item->set('POISONED');
        $pool->save($item);

        self::assertSame('POISONED', $this->buildParser($pool, 7)->transform($markdown));
    }

    public function testImageFragmentIsInvalidatedByMediaVersionBump(): void
    {
        $pool = new ArrayAdapter();
        $markdown = 'Photo: ![alt](/media/2.jpg)';

        // Prime under media version 0, then poison its entry.
        $this->buildParser($pool, 0)->transform($markdown);
        $key0 = 'pw_md.'.hash('xxh3', '2m0|'.$markdown);
        $item = $pool->getItem($key0);
        self::assertTrue($item->isHit(), 'image fragment is keyed with the media version');
        $item->set('POISONED');
        $pool->save($item);

        // A bumped media version must miss the poisoned entry and re-render fresh.
        $fresh = $this->buildParser($pool, 7)->transform($markdown);
        self::assertNotSame('POISONED', $fresh);
        self::assertStringContainsString('<picture', $fresh);
    }

    public function testInlineFilterUsesDistinctKeyNamespace(): void
    {
        $pool = new ArrayAdapter();
        $parser = $this->buildParser($pool);

        $markdown = 'A **bold** claim';

        // Prime the inline cache, then poison the stored value: a hit must return it.
        $parser->transformInline($markdown);
        $key = 'pw_mdi.'.hash('xxh3', '2|'.$markdown);
        $item = $pool->getItem($key);
        self::assertTrue($item->isHit(), 'inline fragment should be cached under the pw_mdi. namespace');
        $item->set('POISONED');
        $pool->save($item);

        self::assertSame('POISONED', $parser->transformInline($markdown));

        // The block filter must not see the inline entry for the same source.
        self::assertSame('<p>A <strong>bold</strong> claim</p>', trim($parser->transform($markdown)));
    }

    public function testInlineImageFragmentIsKeyedWithMediaVersion(): void
    {
        $pool = new ArrayAdapter();
        $markdown = 'Photo: ![alt](/media/2.jpg)';

        $this->buildParser($pool, 4)->transformInline($markdown);

        $key = 'pw_mdi.'.hash('xxh3', '2m4|'.$markdown);
        self::assertTrue($pool->getItem($key)->isHit(), 'inline image fragment is keyed with the media version');
    }

    public function testCacheBackendFailureNeverBreaksRendering(): void
    {
        $brokenPool = new class extends ArrayAdapter {
            public function getItem(mixed $key): CacheItem
            {
                throw new RuntimeException('cache backend down');
            }
        };
        $parser = $this->buildParser($brokenPool);

        self::assertSame("<p>A <strong>bold</strong> claim</p>\n", $parser->transform('A **bold** claim'));
        self::assertSame('A <strong>bold</strong> claim', $parser->transformInline('A **bold** claim'));
    }

    public function testDateShortcodeIsCached(): void
    {
        $pool = new ArrayAdapter();
        $parser = $this->buildParser($pool);

        $markdown = 'Year: date(Y)';
        $first = $parser->transform($markdown);
        $second = $parser->transform($markdown);

        self::assertSame($first, $second);
        self::assertNotSame([], $pool->getValues(), 'date() content is cached too (slight staleness is acceptable)');
    }
}
