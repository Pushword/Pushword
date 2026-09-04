<?php

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
}
