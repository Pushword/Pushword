<?php

declare(strict_types=1);

namespace Pushword\StaticGenerator\Tests\Cache;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
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

#[AllowMockObjectsWithoutExpectations]
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

        $invalidator->postFlush();
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

        $invalidator->postFlush();
    }

    public function testNoDispatchWhenSiteIsNotCacheMode(): void
    {
        $page = $this->makePersistedPage('localhost.dev');
        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'none');

        $this->bus->expects($this->never())->method('dispatch');
        $this->fileManager->expects($this->never())->method('delete');

        $invalidator->postPersist($page);

        $invalidator->postFlush();
        $invalidator->postUpdate($page);
        $invalidator->postFlush();
    }

    public function testNoDispatchWhenSiteUnknown(): void
    {
        $page = $this->makePersistedPage('unknown.host');
        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'static');

        $this->bus->expects($this->never())->method('dispatch');
        $this->fileManager->expects($this->never())->method('delete');

        $invalidator->postPersist($page);

        $invalidator->postFlush();
    }

    public function testNoDispatchWhenSuppressed(): void
    {
        $page = $this->makePersistedPage('localhost.dev');
        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'static');

        $this->bus->expects($this->never())->method('dispatch');
        $this->fileManager->expects($this->never())->method('delete');

        $this->suppressor->suppress(static function () use ($invalidator, $page): void {
            $invalidator->postPersist($page);
            $invalidator->postFlush();
            $invalidator->postUpdate($page);
            $invalidator->postFlush();
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

        $invalidator->postFlush();
    }

    public function testNoDispatchWhenPageIdIsNull(): void
    {
        $page = new Page();
        $page->host = 'localhost.dev';

        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'static');

        $this->bus->expects($this->never())->method('dispatch');
        $this->fileManager->expects($this->never())->method('delete');

        $invalidator->postPersist($page);

        $invalidator->postFlush();
    }

    // --- preRemove ---

    public function testPreRemoveDeletesFileForCacheSite(): void
    {
        $page = $this->makePersistedPage('localhost.dev');
        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'static');

        $this->fileManager->expects($this->once())->method('delete')->with(self::callback(static fn (Page $removed): bool => 'localhost.dev' === $removed->host && '' === $removed->slug));
        $this->bus->expects($this->never())->method('dispatch');

        $invalidator->preRemove($page);

        $invalidator->postFlush();
    }

    public function testPreRemoveSkipsWhenNotCacheMode(): void
    {
        $page = $this->makePersistedPage('localhost.dev');
        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'none');

        $this->fileManager->expects($this->never())->method('delete');
        $this->bus->expects($this->never())->method('dispatch');

        $invalidator->preRemove($page);

        $invalidator->postFlush();
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

        $invalidator->postFlush();
    }

    public function testPersistingADraftDoesNotBump(): void
    {
        $page = $this->makePersistedPage('localhost.dev');
        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'static');

        $this->bus->method('dispatch')->willReturn(new Envelope(new PageCacheRefreshMessage(42)));
        $this->renderEpoch->expects($this->never())->method('bump');

        $invalidator->postPersist($page);

        $invalidator->postFlush();
    }

    public function testMetadataChangeOnPublishedPageBumps(): void
    {
        $page = $this->makePersistedPage('localhost.dev', published: true);
        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'static');

        $this->bus->method('dispatch')->willReturn(new Envelope(new PageCacheRefreshMessage(42)));
        $this->renderEpoch->expects($this->once())->method('bump')->with('localhost.dev');

        $invalidator->preUpdate($page, $this->makePreUpdateArgs($page, ['title' => ['Old', 'New']]));
        $invalidator->postUpdate($page);
        $invalidator->postFlush();
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
        $invalidator->postFlush();
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
        $invalidator->postFlush();
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
        $invalidator->postFlush();
    }

    public function testDraftMetadataChangeDoesNotBump(): void
    {
        $page = $this->makePersistedPage('localhost.dev');
        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'static');

        $this->bus->method('dispatch')->willReturn(new Envelope(new PageCacheRefreshMessage(42)));
        $this->renderEpoch->expects($this->never())->method('bump');

        $invalidator->preUpdate($page, $this->makePreUpdateArgs($page, ['title' => ['Old', 'New']]));
        $invalidator->postUpdate($page);
        $invalidator->postFlush();
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
        $invalidator->postFlush();
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
            $invalidator->postFlush();
        });
    }

    public function testRemovingAPublishedPageBumps(): void
    {
        $page = $this->makePersistedPage('localhost.dev', published: true);
        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'static');

        $this->fileManager->expects($this->once())->method('delete')->with(self::callback(static fn (Page $removed): bool => 'localhost.dev' === $removed->host && '' === $removed->slug));
        $this->renderEpoch->expects($this->once())->method('bump')->with('localhost.dev');

        $invalidator->preRemove($page);

        $invalidator->postFlush();
    }

    public function testRemovingADraftDeletesWithoutBump(): void
    {
        $page = $this->makePersistedPage('localhost.dev');
        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'static');

        $this->fileManager->expects($this->once())->method('delete')->with(self::callback(static fn (Page $removed): bool => 'localhost.dev' === $removed->host && '' === $removed->slug));
        $this->renderEpoch->expects($this->never())->method('bump');

        $invalidator->preRemove($page);

        $invalidator->postFlush();
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
        $invalidator->postFlush();
    }

    public function testInvalidationWaitsForFlushAndPrecedesDispatch(): void
    {
        $page = $this->makePersistedPage('localhost.dev');
        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'static');
        $invalidated = false;
        $this->fileManager->expects($this->once())->method('invalidate')->with($page, null)
            ->willReturnCallback(static function () use (&$invalidated): void {
                $invalidated = true;
            });
        $this->bus->expects($this->once())->method('dispatch')
            ->willReturnCallback(static function (object $message) use (&$invalidated): Envelope {
                self::assertTrue($invalidated, 'A synchronous handler must run after invalidation.');

                return new Envelope($message);
            });

        $invalidator->postUpdate($page);
        self::assertFalse($invalidated, 'A failed flush must leave the cache untouched.');
        $invalidator->postFlush();
    }

    public function testResetDiscardsAnUnflushedRefresh(): void
    {
        $page = $this->makePersistedPage('localhost.dev');
        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'static');
        $this->fileManager->expects($this->never())->method('invalidate');
        $this->bus->expects($this->never())->method('dispatch');

        $invalidator->postUpdate($page);
        $invalidator->reset();
        $invalidator->postFlush();
    }

    public function testSlugChangesInvalidateTheOldPathToo(): void
    {
        $page = $this->makePersistedPage('localhost.dev');
        $page->slug = 'new-path';

        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'static');
        $this->fileManager->expects($this->once())->method('invalidate')->with(
            $page,
            self::callback(static fn (Page $previous): bool => 'old-path' === $previous->slug && $previous->host === $page->host),
        );
        $this->bus->method('dispatch')->willReturn(new Envelope(new PageCacheRefreshMessage(42)));

        $invalidator->preUpdate($page, $this->makePreUpdateArgs($page, ['slug' => ['old-path', 'new-path']]));
        $invalidator->postUpdate($page);
        $invalidator->postFlush();
    }

    public function testMovingAwayFromACacheSiteStillInvalidatesThePreviousPath(): void
    {
        $page = $this->makePersistedPage('dynamic.host');
        $invalidator = $this->makeInvalidator(host: 'localhost.dev', cacheMode: 'static');
        $this->fileManager->expects($this->once())->method('invalidate')->with(
            $page,
            self::callback(static fn (Page $previous): bool => 'localhost.dev' === $previous->host),
        );
        $this->bus->expects($this->never())->method('dispatch');

        $invalidator->preUpdate($page, $this->makePreUpdateArgs($page, ['host' => ['localhost.dev', 'dynamic.host']]));
        $invalidator->postUpdate($page);
        $invalidator->postFlush();
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
