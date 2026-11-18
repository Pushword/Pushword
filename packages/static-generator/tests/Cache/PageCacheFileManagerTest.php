<?php

declare(strict_types=1);

namespace Pushword\StaticGenerator\Tests\Cache;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Site\SiteRegistry;
use Pushword\StaticGenerator\Cache\PageCacheFileManager;
use Pushword\StaticGenerator\Generator\CompressionAlgorithm;
use Pushword\StaticGenerator\StaticAppGenerator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

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

    public function testInvalidationRemovesBothPathsAndTheirCompressedFiles(): void
    {
        self::bootKernel();
        $page = new Page();
        $page->host = 'localhost.dev';
        $page->slug = 'old-path';

        $previous = clone $page;
        $page->slug = 'new-path';
        $site = self::getContainer()->get(SiteRegistry::class)->get('localhost.dev');
        $directory = self::getContainer()->get(StaticAppGenerator::class)->getCacheDir($site);
        $filesystem = new Filesystem();

        foreach (['old-path', 'new-path'] as $slug) {
            foreach (CompressionAlgorithm::fileSuffixes() as $suffix) {
                $filesystem->dumpFile($directory.'/'.$slug.'.html'.$suffix, 'stale');
            }
        }

        self::getContainer()->get(PageCacheFileManager::class)->invalidate($page, $previous);
        foreach (['old-path', 'new-path'] as $slug) {
            foreach (CompressionAlgorithm::fileSuffixes() as $suffix) {
                self::assertFileDoesNotExist($directory.'/'.$slug.'.html'.$suffix);
            }
        }
    }
}
