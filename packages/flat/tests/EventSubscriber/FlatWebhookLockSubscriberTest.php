<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests\EventSubscriber;

use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityUpdatedEvent;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\User;
use Pushword\Flat\EventSubscriber\FlatWebhookLockSubscriber;
use Pushword\Flat\Service\FlatLockManager;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class FlatWebhookLockSubscriberTest extends TestCase
{
    private string $tempDir;

    private FlatLockManager $lockManager;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/flat-webhook-test-'.uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->lockManager = new FlatLockManager($this->tempDir, 60, 3600);
    }

    protected function tearDown(): void
    {
        $fs = new Filesystem();
        if (is_dir($this->tempDir)) {
            $fs->remove($this->tempDir);
        }
    }

    private function createSubscriber(): FlatWebhookLockSubscriber
    {
        return new FlatWebhookLockSubscriber($this->lockManager);
    }

    public function testGetSubscribedEventsReturnsCorrectEvents(): void
    {
        $events = FlatWebhookLockSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(BeforeEntityPersistedEvent::class, $events);
        self::assertArrayHasKey(BeforeEntityUpdatedEvent::class, $events);
        self::assertSame('onBeforePersist', $events[BeforeEntityPersistedEvent::class]);
        self::assertSame('onBeforeUpdate', $events[BeforeEntityUpdatedEvent::class]);
    }

    public function testOnBeforePersistAllowsWhenNotLocked(): void
    {
        $page = new Page();
        $page->host = 'example.com';

        /** @var BeforeEntityPersistedEvent<object> $event */
        $event = new BeforeEntityPersistedEvent($page);
        $subscriber = $this->createSubscriber();

        $subscriber->onBeforePersist($event);

        $this->expectNotToPerformAssertions();
    }

    public function testOnBeforePersistThrowsWhenWebhookLocked(): void
    {
        $page = new Page();
        $page->host = 'example.com';

        $this->lockManager->acquireWebhookLock('example.com', 'CI/CD deployment in progress', 3600, 'deploy@ci.com');

        /** @var BeforeEntityPersistedEvent<object> $event */
        $event = new BeforeEntityPersistedEvent($page);
        $subscriber = $this->createSubscriber();

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Content is locked by deploy@ci.com');

        $subscriber->onBeforePersist($event);
    }

    public function testOnBeforeUpdateThrowsWhenWebhookLocked(): void
    {
        $page = new Page();
        $page->host = 'example.com';

        $this->lockManager->acquireWebhookLock('example.com', 'External sync', 3600);

        /** @var BeforeEntityUpdatedEvent<object> $event */
        $event = new BeforeEntityUpdatedEvent($page);
        $subscriber = $this->createSubscriber();

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Content is locked by external system: External sync');

        $subscriber->onBeforeUpdate($event);
    }

    public function testIgnoresNonPageNonMediaEntities(): void
    {
        $user = new User();
        $user->email = 'test@example.com';

        $this->lockManager->acquireWebhookLock(null, 'Test lock');

        /** @var BeforeEntityPersistedEvent<object> $event */
        $event = new BeforeEntityPersistedEvent($user);
        $subscriber = $this->createSubscriber();

        $subscriber->onBeforePersist($event);

        $this->expectNotToPerformAssertions();
    }

    public function testMediaEntityReturnsNullHost(): void
    {
        $media = new Media();
        $media->setFileName('test-image.jpg');

        $this->lockManager->acquireWebhookLock('somehost.com', 'Test lock');

        /** @var BeforeEntityPersistedEvent<object> $event */
        $event = new BeforeEntityPersistedEvent($media);
        $subscriber = $this->createSubscriber();

        $subscriber->onBeforePersist($event);

        $this->expectNotToPerformAssertions();
    }

    public function testExceptionMessageContainsLockInfo(): void
    {
        $page = new Page();
        $page->host = 'test.example.com';

        $this->lockManager->acquireWebhookLock('test.example.com', 'Git workflow in progress', 3600, 'git-webhook@company.com');

        /** @var BeforeEntityUpdatedEvent<object> $event */
        $event = new BeforeEntityUpdatedEvent($page);
        $subscriber = $this->createSubscriber();

        try {
            $subscriber->onBeforeUpdate($event);
            self::fail('Expected AccessDeniedHttpException');
        } catch (AccessDeniedHttpException $accessDeniedHttpException) {
            self::assertStringContainsString('git-webhook@company.com', $accessDeniedHttpException->getMessage());
            self::assertStringContainsString('Git workflow in progress', $accessDeniedHttpException->getMessage());
            self::assertStringContainsString('View-only mode active', $accessDeniedHttpException->getMessage());
        }
    }

    public function testAllowsUpdateWhenNotWebhookLocked(): void
    {
        $page = new Page();
        $page->host = 'example.com';

        /** @var BeforeEntityUpdatedEvent<object> $event */
        $event = new BeforeEntityUpdatedEvent($page);
        $subscriber = $this->createSubscriber();

        $subscriber->onBeforeUpdate($event);

        $this->expectNotToPerformAssertions();
    }

    public function testPageWithNullHostIsChecked(): void
    {
        $page = new Page();
        $page->host = null;

        /** @var BeforeEntityPersistedEvent<object> $event */
        $event = new BeforeEntityPersistedEvent($page);
        $subscriber = $this->createSubscriber();

        $subscriber->onBeforePersist($event);

        $this->expectNotToPerformAssertions();
    }

    public function testAllowsWhenManualLockNotWebhookLock(): void
    {
        $page = new Page();
        $page->host = 'example.com';

        $this->lockManager->acquireLock('example.com', FlatLockManager::LOCK_TYPE_MANUAL);

        /** @var BeforeEntityPersistedEvent<object> $event */
        $event = new BeforeEntityPersistedEvent($page);
        $subscriber = $this->createSubscriber();

        $subscriber->onBeforePersist($event);

        $this->expectNotToPerformAssertions();
    }
}
