<?php

namespace Pushword\Core\Tests\Service;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Service\LinkProvider;
use Pushword\Core\Service\Markdown\MarkdownParser;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Twig\MediaExtension;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Guards the markdown render cache: it must be transparent (identical output)
 * and actually serve from cache on a hit.
 */
#[Group('integration')]
final class MarkdownParserCacheTest extends KernelTestCase
{
    private function buildParser(ArrayAdapter $pool): MarkdownParser
    {
        self::bootKernel();
        $container = self::getContainer();

        return new MarkdownParser(
            $container->get(LinkProvider::class),
            $container->get(MediaExtension::class),
            $container->get(SiteRegistry::class),
            $pool,
            new ArrayAdapter(),
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
        $key = 'pw_md.'.hash('xxh3', '1m0|'.$markdown);
        $item = $pool->getItem($key);
        self::assertTrue($item->isHit(), 'fragment should be cached under the expected key');
        $item->set('POISONED');
        $pool->save($item);

        self::assertSame('POISONED', $parser->transform($markdown));
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
