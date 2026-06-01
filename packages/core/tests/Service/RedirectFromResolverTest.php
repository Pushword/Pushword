<?php

namespace Pushword\Core\Tests\Service;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\RedirectFromResolver;

final class RedirectFromResolverTest extends TestCase
{
    public function testClassification(): void
    {
        $target = $this->page('blog/cms-comparison', 'Real content');

        $pages = [
            $target,
            $this->page('cms-comparison', 'Location: /blog/cms-comparison 301'), // internal → folds
            $this->page('moved-temp', 'Location: /blog/cms-comparison 302'),      // internal, keeps code
            $this->page('go-ext', 'Location: https://example.com'),               // external → keep phantom
            $this->page('dangling', 'Location: /nowhere 301'),                    // dangling → keep phantom
            $this->page('chain', 'Location: /cms-comparison 301'),                // target is a redirect → keep
        ];

        $result = new RedirectFromResolver()->resolve($pages);

        self::assertSame(
            ['cms-comparison' => 301, 'moved-temp' => 302],
            $result['reverse']['blog/cms-comparison'],
        );
        self::assertArrayHasKey('cms-comparison', $result['foldedSlugs']);
        self::assertArrayHasKey('moved-temp', $result['foldedSlugs']);
        self::assertArrayNotHasKey('go-ext', $result['foldedSlugs']);
        self::assertArrayNotHasKey('dangling', $result['foldedSlugs']);
        self::assertArrayNotHasKey('chain', $result['foldedSlugs']);
    }

    private function page(string $slug, string $mainContent): Page
    {
        $page = new Page(false);
        $page->host = 'localhost.dev';
        $page->setSlug($slug);
        $page->setMainContent($mainContent);

        return $page;
    }
}
