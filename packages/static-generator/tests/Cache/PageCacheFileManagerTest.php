<?php

declare(strict_types=1);

namespace Pushword\StaticGenerator\Tests\Cache;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\StaticGenerator\Cache\PageCacheFileManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class PageCacheFileManagerTest extends KernelTestCase
{
    public function testDeleteRejectsSlugOutsideCacheDirectory(): void
    {
        self::bootKernel();

        $page = new Page();
        $page->host = 'localhost.dev';
        $page->slug = '../../outside-cache';

        $this->expectException(InvalidArgumentException::class);

        self::getContainer()->get(PageCacheFileManager::class)->delete($page);
    }

    /** Cached, /404 would answer 200: PHP serves it with its 404 status instead. */
    public function testTheErrorPageIsNeverCacheable(): void
    {
        self::bootKernel();
        $fileManager = self::getContainer()->get(PageCacheFileManager::class);

        $page = new Page();
        $page->host = 'localhost.dev';
        $page->slug = 'about';
        self::assertTrue($fileManager->isCacheable($page));

        $page->slug = '404';
        self::assertFalse($fileManager->isCacheable($page));
    }
}
