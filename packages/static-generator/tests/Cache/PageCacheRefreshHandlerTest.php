<?php

declare(strict_types=1);

namespace Pushword\StaticGenerator\Tests\Cache;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\StaticGenerator\Cache\Message\PageCacheRefreshMessage;
use Pushword\StaticGenerator\Cache\MessageHandler\PageCacheRefreshHandler;
use Pushword\StaticGenerator\Cache\PageCacheFileManager;
use Pushword\StaticGenerator\Cache\PageCacheGeneratorInterface;

final class PageCacheRefreshHandlerTest extends TestCase
{
    /** @var PageRepository&MockObject */
    private MockObject $pageRepository;

    /** @var PageCacheGeneratorInterface&MockObject */
    private MockObject $generator;

    /** @var PageCacheFileManager&MockObject */
    private MockObject $fileManager;

    private PageCacheRefreshHandler $handler;

    protected function setUp(): void
    {
        $this->pageRepository = $this->createMock(PageRepository::class);
        $this->generator = $this->createMock(PageCacheGeneratorInterface::class);
        $this->fileManager = $this->createMock(PageCacheFileManager::class);

        $this->handler = new PageCacheRefreshHandler(
            pageRepository: $this->pageRepository,
            generator: $this->generator,
            fileManager: $this->fileManager,
        );
    }

    public function testNoopWhenPageNotFound(): void
    {
        $this->pageRepository->method('find')->willReturn(null);

        $this->generator->expects($this->never())->method('generatePage');
        $this->fileManager->expects($this->never())->method('delete');

        ($this->handler)(new PageCacheRefreshMessage(9999));
    }

    public function testGeneratesPageWhenCacheable(): void
    {
        $page = $this->makePage(host: 'example.com', slug: 'about');
        $this->pageRepository->method('find')->willReturn($page);
        $this->fileManager->method('isCacheable')->willReturn(true);

        $this->generator->expects($this->once())
            ->method('generatePage')
            ->with('example.com', 'about');

        ($this->handler)(new PageCacheRefreshMessage(1));
    }

    public function testDeletesFileWhenPageNotCacheable(): void
    {
        $page = $this->makePage(host: 'example.com', slug: 'about');
        $this->pageRepository->method('find')->willReturn($page);
        $this->fileManager->method('isCacheable')->willReturn(false);

        $this->fileManager->expects($this->once())->method('delete')->with($page);
        $this->generator->expects($this->never())->method('generatePage');

        ($this->handler)(new PageCacheRefreshMessage(1));
    }

    public function testUsesRealSlugForHomepage(): void
    {
        $page = $this->makePage(host: 'example.com', slug: 'homepage');
        $this->pageRepository->method('find')->willReturn($page);
        $this->fileManager->method('isCacheable')->willReturn(true);

        // getRealSlug() returns '' for the homepage slug
        $this->generator->expects($this->once())
            ->method('generatePage')
            ->with('example.com', '');

        ($this->handler)(new PageCacheRefreshMessage(1));
    }

    private function makePage(string $host, string $slug): Page
    {
        $page = new Page();
        $page->host = $host;
        $page->setSlug($slug);

        return $page;
    }
}
