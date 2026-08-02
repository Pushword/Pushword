<?php

namespace Pushword\StaticGenerator\Tests\Cache;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Cache\PageCacheSuppressor;
use Pushword\Core\Cache\RenderEpoch;
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

    /** @var RenderEpoch&MockObject */
    private MockObject $renderEpoch;

    protected function setUp(): void
    {
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->suppressor = new PageCacheSuppressor();
        $this->fileManager = $this->createMock(PageCacheFileManager::class);
        $this->renderEpoch = $this->createMock(RenderEpoch::class);
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
        $this->fileManager->expects($this->never())->method('delete');

        $invalidator->postPersist($page);
    }

    public function testPostUpdateDispatchesMessageForCacheSite(): void
    {
        $page = $this->makePersistedPage('localhost.dev');
        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'static');

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new PageCacheRefreshMessage((int) $page->id)));
        $this->fileManager->expects($this->never())->method('delete');

        $invalidator->postUpdate($page);
    }

    public function testNoDispatchWhenSiteIsNotCacheMode(): void
    {
        $page = $this->makePersistedPage('localhost.dev');
        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'none');

        $this->bus->expects($this->never())->method('dispatch');
        $this->fileManager->expects($this->never())->method('delete');

        $invalidator->postPersist($page);
        $invalidator->postUpdate($page);
    }

    public function testNoDispatchWhenSiteUnknown(): void
    {
        $page = $this->makePersistedPage('unknown.host');
        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'static');

        $this->bus->expects($this->never())->method('dispatch');
        $this->fileManager->expects($this->never())->method('delete');

        $invalidator->postPersist($page);
    }

    public function testNoDispatchWhenSuppressed(): void
    {
        $page = $this->makePersistedPage('localhost.dev');
        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'static');

        $this->bus->expects($this->never())->method('dispatch');
        $this->fileManager->expects($this->never())->method('delete');

        $this->suppressor->suppress(static function () use ($invalidator, $page): void {
            $invalidator->postPersist($page);
            $invalidator->postUpdate($page);
        });
    }

    public function testNoDispatchWhenPageIsHeld(): void
    {
        $page = $this->makePersistedPage('localhost.dev');
        $page->setHoldPublication(true);

        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'static');

        // A held page keeps its current static file: neither refreshed nor deleted.
        $this->bus->expects($this->never())->method('dispatch');
        $this->fileManager->expects($this->never())->method('delete');

        $invalidator->postUpdate($page);
    }

    public function testNoDispatchWhenPageIdIsNull(): void
    {
        $page = new Page();
        $page->host = 'localhost.dev';

        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'static');

        $this->bus->expects($this->never())->method('dispatch');
        $this->fileManager->expects($this->never())->method('delete');

        $invalidator->postPersist($page);
    }

    // --- preRemove ---

    public function testPreRemoveDeletesFileForCacheSite(): void
    {
        $page = $this->makePersistedPage('localhost.dev');
        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'static');

        $this->fileManager->expects($this->once())->method('delete')->with($page);
        $this->bus->expects($this->never())->method('dispatch');

        $invalidator->preRemove($page);
    }

    public function testPreRemoveSkipsWhenNotCacheMode(): void
    {
        $page = $this->makePersistedPage('localhost.dev');
        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'none');

        $this->fileManager->expects($this->never())->method('delete');
        $this->bus->expects($this->never())->method('dispatch');

        $invalidator->preRemove($page);
    }

    public function testSuppressedRemoveSkipsFileDeleteButBumps(): void
    {
        // `--force` flat imports delete every page before re-importing it: the
        // static output must survive that reset, but listings still go stale.
        $page = $this->makePersistedPage('localhost.dev', published: true);
        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'static');

        $this->fileManager->expects($this->never())->method('delete');
        $this->bus->expects($this->never())->method('dispatch');
        $this->renderEpoch->expects($this->once())->method('bump')->with('localhost.dev');

        $this->suppressor->suppress(static fn () => $invalidator->preRemove($page));
    }

    // --- listing-relevance → epoch bump (Phase 2) ---

    public function testPersistingAPublishedPageBumpsTheEpoch(): void
    {
        $page = $this->makePersistedPage('localhost.dev', published: true);
        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'static');

        $this->bus->method('dispatch')->willReturn(new Envelope(new PageCacheRefreshMessage(42)));
        $this->renderEpoch->expects($this->once())->method('bump')->with('localhost.dev');

        $invalidator->postPersist($page);
    }

    public function testPersistingADraftDoesNotBump(): void
    {
        $page = $this->makePersistedPage('localhost.dev');
        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'static');

        $this->bus->method('dispatch')->willReturn(new Envelope(new PageCacheRefreshMessage(42)));
        $this->renderEpoch->expects($this->never())->method('bump');

        $invalidator->postPersist($page);
    }

    public function testMetadataChangeOnPublishedPageBumps(): void
    {
        $page = $this->makePersistedPage('localhost.dev', published: true);
        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'static');

        $this->bus->method('dispatch')->willReturn(new Envelope(new PageCacheRefreshMessage(42)));
        $this->renderEpoch->expects($this->once())->method('bump')->with('localhost.dev');

        $invalidator->preUpdate($page, $this->makePreUpdateArgs($page, ['title' => ['Old', 'New']]));
        $invalidator->postUpdate($page);
    }

    public function testBumpHappensEvenWhenTheHostIsNotCacheMode(): void
    {
        // Full-static (GA-class) hosts consume the epoch via cron incremental:
        // the bump must not be gated on cache mode, only the refresh message is.
        $page = $this->makePersistedPage('localhost.dev', published: true);
        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'none');

        $this->bus->expects($this->never())->method('dispatch');
        $this->renderEpoch->expects($this->once())->method('bump')->with('localhost.dev');

        $invalidator->preUpdate($page, $this->makePreUpdateArgs($page, ['slug' => ['old-slug', 'new-slug']]));
        $invalidator->postUpdate($page);
    }

    public function testProseOnlyContentEditDoesNotBump(): void
    {
        $page = $this->makePersistedPage('localhost.dev', published: true);
        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'static');

        $this->bus->method('dispatch')->willReturn(new Envelope(new PageCacheRefreshMessage(42)));
        $this->renderEpoch->expects($this->never())->method('bump');

        $changeSet = ['mainContent' => ['Some text with a [link](/about).', 'Rewritten text, same [link](/about).']];
        $invalidator->preUpdate($page, $this->makePreUpdateArgs($page, $changeSet));
        $invalidator->postUpdate($page);
    }

    public function testContentEditChangingTheLinkSetBumps(): void
    {
        $page = $this->makePersistedPage('localhost.dev', published: true);
        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'static');

        $this->bus->method('dispatch')->willReturn(new Envelope(new PageCacheRefreshMessage(42)));
        $this->renderEpoch->expects($this->once())->method('bump')->with('localhost.dev');

        $changeSet = ['mainContent' => ['No links here.', 'Now with a [link](/about).']];
        $invalidator->preUpdate($page, $this->makePreUpdateArgs($page, $changeSet));
        $invalidator->postUpdate($page);
    }

    public function testDraftMetadataChangeDoesNotBump(): void
    {
        $page = $this->makePersistedPage('localhost.dev');
        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'static');

        $this->bus->method('dispatch')->willReturn(new Envelope(new PageCacheRefreshMessage(42)));
        $this->renderEpoch->expects($this->never())->method('bump');

        $invalidator->preUpdate($page, $this->makePreUpdateArgs($page, ['title' => ['Old', 'New']]));
        $invalidator->postUpdate($page);
    }

    public function testUnpublishingBumps(): void
    {
        // publishedAt → null: the page must drop out of every listing. The page
        // reads as unpublished already, the changeset carries the transition.
        $page = $this->makePersistedPage('localhost.dev');
        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'static');

        $this->bus->method('dispatch')->willReturn(new Envelope(new PageCacheRefreshMessage(42)));
        $this->renderEpoch->expects($this->once())->method('bump')->with('localhost.dev');

        $invalidator->preUpdate($page, $this->makePreUpdateArgs($page, ['publishedAt' => [new DateTime('-1 day'), null]]));
        $invalidator->postUpdate($page);
    }

    public function testSuppressedPersistBumpsWithoutDispatching(): void
    {
        // A bulk flat import mutes the per-page messages, but the epoch must
        // still move: the deploy chain's `pw:static --incremental` reads it to
        // know listings need a resweep.
        $page = $this->makePersistedPage('localhost.dev', published: true);
        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'static');

        $this->bus->expects($this->never())->method('dispatch');
        $this->renderEpoch->expects($this->once())->method('bump')->with('localhost.dev');

        $this->suppressor->suppress(static fn () => $invalidator->postPersist($page));
    }

    public function testSuppressedUpdateBumpsWithoutDispatching(): void
    {
        $page = $this->makePersistedPage('localhost.dev', published: true);
        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'static');

        $this->bus->expects($this->never())->method('dispatch');
        $this->renderEpoch->expects($this->once())->method('bump')->with('localhost.dev');

        $preUpdateArgs = $this->makePreUpdateArgs($page, ['title' => ['Old', 'New']]);
        $this->suppressor->suppress(static function () use ($invalidator, $page, $preUpdateArgs): void {
            $invalidator->preUpdate($page, $preUpdateArgs);
            $invalidator->postUpdate($page);
        });
    }

    public function testRemovingAPublishedPageBumps(): void
    {
        $page = $this->makePersistedPage('localhost.dev', published: true);
        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'static');

        $this->fileManager->expects($this->once())->method('delete')->with($page);
        $this->renderEpoch->expects($this->once())->method('bump')->with('localhost.dev');

        $invalidator->preRemove($page);
    }

    public function testRemovingADraftDeletesWithoutBump(): void
    {
        $page = $this->makePersistedPage('localhost.dev');
        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'static');

        $this->fileManager->expects($this->once())->method('delete')->with($page);
        $this->renderEpoch->expects($this->never())->method('bump');

        $invalidator->preRemove($page);
    }

    public function testResetDrainsPendingRelevance(): void
    {
        $page = $this->makePersistedPage('localhost.dev', published: true);
        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'static');

        $this->bus->method('dispatch')->willReturn(new Envelope(new PageCacheRefreshMessage(42)));
        $this->renderEpoch->expects($this->never())->method('bump');

        $invalidator->preUpdate($page, $this->makePreUpdateArgs($page, ['title' => ['Old', 'New']]));
        $invalidator->reset();
        $invalidator->postUpdate($page);
    }

    // --- helpers ---

    /**
     * @param array<string, array{mixed, mixed}> $changeSet
     */
    private function makePreUpdateArgs(Page $page, array $changeSet): PreUpdateEventArgs
    {
        return new PreUpdateEventArgs($page, self::createStub(EntityManagerInterface::class), $changeSet);
    }

    private function makeInvalidator(string $host, string $cacheMode): PageCacheInvalidator
    {
        $registry = $this->makeRegistry($host, $cacheMode);

        return new PageCacheInvalidator($this->bus, $registry, $this->suppressor, $this->fileManager, $this->renderEpoch);
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

    private function makePersistedPage(string $host, bool $published = false): Page
    {
        $page = new Page();
        $page->host = $host;
        // new Page() defaults publishedAt to now — a draft needs it nulled.
        $page->publishedAt = $published ? new DateTime('-1 day') : null;

        // id has asymmetric visibility (private(set)) — set via Reflection to simulate a persisted entity.
        $ref = new ReflectionProperty(Page::class, 'id');
        $ref->setValue($page, 42);

        return $page;
    }
}
