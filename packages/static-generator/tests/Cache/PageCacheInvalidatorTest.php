<?php

namespace Pushword\StaticGenerator\Tests\Cache;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Cache\PageCacheSuppressor;
use Pushword\Core\Entity\Page;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Template\TemplateResolver;
use Pushword\StaticGenerator\Cache\Message\PageCacheRefreshMessage;
use Pushword\StaticGenerator\Cache\PageCacheFileManager;
use Pushword\StaticGenerator\Cache\PageCacheInvalidator;
use ReflectionProperty;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Twig\Environment as Twig;
use Twig\Loader\FilesystemLoader;

final class PageCacheInvalidatorTest extends TestCase
{
    /** @var MessageBusInterface&MockObject */
    private MockObject $bus;

    private PageCacheSuppressor $suppressor;

    /** @var PageCacheFileManager&MockObject */
    private MockObject $fileManager;

    protected function setUp(): void
    {
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->suppressor = new PageCacheSuppressor();
        $this->fileManager = $this->createMock(PageCacheFileManager::class);
    }

    // --- postPersist / postUpdate ---

    public function testPostPersistDispatchesMessageForCacheSite(): void
    {
        $page = $this->makePersistedPage('localhost.dev');
        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'static');

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with(self::isInstanceOf(PageCacheRefreshMessage::class))
            ->willReturn(new Envelope(new PageCacheRefreshMessage((int) $page->id)));

        $invalidator->postPersist($page);
    }

    public function testPostUpdateDispatchesMessageForCacheSite(): void
    {
        $page = $this->makePersistedPage('localhost.dev');
        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'static');

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new PageCacheRefreshMessage((int) $page->id)));

        $invalidator->postUpdate($page);
    }

    public function testNoDispatchWhenSiteIsNotCacheMode(): void
    {
        $page = $this->makePersistedPage('localhost.dev');
        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'none');

        $this->bus->expects($this->never())->method('dispatch');

        $invalidator->postPersist($page);
        $invalidator->postUpdate($page);
    }

    public function testNoDispatchWhenSiteUnknown(): void
    {
        $page = $this->makePersistedPage('unknown.host');
        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'static');

        $this->bus->expects($this->never())->method('dispatch');

        $invalidator->postPersist($page);
    }

    public function testNoDispatchWhenSuppressed(): void
    {
        $page = $this->makePersistedPage('localhost.dev');
        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'static');

        $this->bus->expects($this->never())->method('dispatch');

        $this->suppressor->suppress(static function () use ($invalidator, $page): void {
            $invalidator->postPersist($page);
            $invalidator->postUpdate($page);
        });
    }

    public function testNoDispatchWhenPageIdIsNull(): void
    {
        $page = new Page();
        $page->host = 'localhost.dev';

        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'static');

        $this->bus->expects($this->never())->method('dispatch');

        $invalidator->postPersist($page);
    }

    // --- preRemove ---

    public function testPreRemoveDeletesFileForCacheSite(): void
    {
        $page = $this->makePersistedPage('localhost.dev');
        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'static');

        $this->fileManager->expects($this->once())->method('delete')->with($page);

        $invalidator->preRemove($page);
    }

    public function testPreRemoveSkipsWhenNotCacheMode(): void
    {
        $page = $this->makePersistedPage('localhost.dev');
        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'none');

        $this->fileManager->expects($this->never())->method('delete');

        $invalidator->preRemove($page);
    }

    public function testPreRemoveSkipsWhenSuppressed(): void
    {
        $page = $this->makePersistedPage('localhost.dev');
        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'static');

        $this->fileManager->expects($this->never())->method('delete');

        $this->suppressor->suppress(static fn () => $invalidator->preRemove($page));
    }

    // --- helpers ---

    private function makeInvalidator(string $host, string $cacheMode): PageCacheInvalidator
    {
        $registry = $this->makeRegistry($host, $cacheMode);

        return new PageCacheInvalidator($this->bus, $registry, $this->suppressor, $this->fileManager);
    }

    private function makeRegistry(string $host, string $cacheMode): SiteRegistry
    {
        $params = new ParameterBag(['kernel.project_dir' => sys_get_temp_dir()]);
        $templateResolver = new TemplateResolver(new Twig(new FilesystemLoader()), new ArrayAdapter());

        return new SiteRegistry(
            [$host => [
                'hosts' => [$host],
                'base_url' => 'https://'.$host,
                'name' => 'Test',
                'locale' => 'en',
                'locales' => 'en',
                'template' => '@Pushword',
                'entity_can_override_filters' => false,
                'cache' => $cacheMode,
            ]],
            $templateResolver,
            $params,
        );
    }

    private function makePersistedPage(string $host): Page
    {
        $page = new Page();
        $page->host = $host;

        // id has asymmetric visibility (private(set)) — set via Reflection to simulate a persisted entity.
        $ref = new ReflectionProperty(Page::class, 'id');
        $ref->setValue($page, 42);

        return $page;
    }
}
