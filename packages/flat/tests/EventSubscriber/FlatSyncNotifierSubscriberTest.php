<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests\EventSubscriber;

use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Flat\Admin\FlatSyncNotifier;
use Pushword\Flat\EventSubscriber\FlatSyncNotifierSubscriber;
use Pushword\Flat\Service\FlatLockManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

#[Group('integration')]
final class FlatSyncNotifierSubscriberTest extends KernelTestCase
{
    private const string TEST_HOST = 'localhost.dev';

    private Session $session;

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();

        $request = new Request();
        $this->session = new Session(new MockArraySessionStorage());
        $request->setSession($this->session);

        /** @var RequestStack $requestStack */
        $requestStack = self::getContainer()->get(RequestStack::class);
        $requestStack->push($request);

        // Set up a lock so notifyAll() produces an observable flash message
        /** @var FlatLockManager $lockManager */
        $lockManager = self::getContainer()->get(FlatLockManager::class);
        $lockManager->acquireLock(self::TEST_HOST, 'manual');
    }

    #[Override]
    protected function tearDown(): void
    {
        /** @var FlatLockManager $lockManager */
        $lockManager = self::getContainer()->get(FlatLockManager::class);
        $lockManager->releaseLock(self::TEST_HOST);

        parent::tearDown();
    }

    public function testGetSubscribedEventsReturnsKernelRequest(): void
    {
        $events = FlatSyncNotifierSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(KernelEvents::REQUEST, $events);
    }

    public function testCallsNotifyAllOnAdminMainRequest(): void
    {
        $subscriber = $this->createSubscriber();
        $event = $this->createRequestEvent('/admin/dashboard', HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertNotEmpty($this->session->getFlashBag()->get('warning'));
    }

    public function testSkipsNonAdminRoutes(): void
    {
        $subscriber = $this->createSubscriber();
        $event = $this->createRequestEvent('/page/hello', HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertSame([], $this->session->getFlashBag()->peekAll());
    }

    public function testSkipsSubRequests(): void
    {
        $subscriber = $this->createSubscriber();
        $event = $this->createRequestEvent('/admin/dashboard', HttpKernelInterface::SUB_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertSame([], $this->session->getFlashBag()->peekAll());
    }

    private function createSubscriber(): FlatSyncNotifierSubscriber
    {
        /** @var FlatSyncNotifier $notifier */
        $notifier = self::getContainer()->get(FlatSyncNotifier::class);

        return new FlatSyncNotifierSubscriber($notifier);
    }

    private function createRequestEvent(string $uri, int $requestType): RequestEvent
    {
        $kernel = self::$kernel;
        \assert($kernel instanceof HttpKernelInterface);

        return new RequestEvent($kernel, Request::create($uri), $requestType);
    }
}
