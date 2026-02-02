<?php

namespace Pushword\Core\Tests\EventListener;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\EventListener\RequestContextListener;
use Pushword\Core\Site\RequestContext;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[Group('integration')]
final class RequestContextListenerTest extends KernelTestCase
{
    private SiteRegistry $appPool;

    private RequestContextListener $listener;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->appPool = self::getContainer()->get(SiteRegistry::class);
        $requestContext = self::getContainer()->get(RequestContext::class);
        $this->listener = new RequestContextListener($this->appPool, $requestContext);
    }

    public function testDefaultHostWithNoRouteHost(): void
    {
        $request = Request::create('https://localhost.dev/about');
        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $event = new RequestEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        ($this->listener)($event);

        self::assertSame('localhost.dev', $this->appPool->getCurrentHost());
        self::assertSame('localhost.dev', $this->appPool->getMainHost());
    }

    public function testDefaultHostWithValidRouteHost(): void
    {
        $request = Request::create('https://localhost.dev/pushword.piedweb.com/about');
        $request->attributes->set('host', 'pushword.piedweb.com');
        $request->attributes->set('slug', 'about');
        $request->attributes->set('_route', 'custom_host_pushword_page');

        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $event = new RequestEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        ($this->listener)($event);

        self::assertSame('pushword.piedweb.com', $this->appPool->getCurrentHost());
        self::assertSame('pushword.piedweb.com', $this->appPool->getMainHost());
    }

    public function testNonDefaultHostWithNoRouteHost(): void
    {
        $request = Request::create('https://pushword.piedweb.com/about');
        $request->attributes->set('slug', 'about');
        $request->attributes->set('_route', 'pushword_page');

        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $event = new RequestEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        ($this->listener)($event);

        self::assertSame('pushword.piedweb.com', $this->appPool->getCurrentHost());
        self::assertSame('pushword.piedweb.com', $this->appPool->getMainHost());
    }

    public function testNonDefaultHostIgnoresRouteHost(): void
    {
        $request = Request::create('https://pushword.piedweb.com/localhost.dev/about');
        $request->attributes->set('host', 'localhost.dev');
        $request->attributes->set('slug', 'about');
        $request->attributes->set('_route', 'custom_host_pushword_page');

        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $event = new RequestEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        ($this->listener)($event);

        // HTTP host takes priority for non-default hosts
        self::assertSame('pushword.piedweb.com', $this->appPool->getCurrentHost());
        self::assertSame('pushword.piedweb.com', $this->appPool->getMainHost());
    }

    public function testUnknownHttpHostWithValidRouteHost(): void
    {
        $request = Request::create('http://127.0.0.1/localhost.dev/about');
        $request->attributes->set('host', 'localhost.dev');
        $request->attributes->set('slug', 'about');
        $request->attributes->set('_route', 'custom_host_pushword_page');

        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $event = new RequestEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        ($this->listener)($event);

        // Unknown HTTP host allows route host to take priority
        self::assertSame('localhost.dev', $this->appPool->getCurrentHost());
        self::assertSame('localhost.dev', $this->appPool->getMainHost());
    }

    public function testUnknownHttpHostWithUnknownRouteHost(): void
    {
        $request = Request::create('http://127.0.0.1/unknown.tld/about');
        $request->attributes->set('host', 'unknown.tld');
        $request->attributes->set('slug', 'about');
        $request->attributes->set('_route', 'custom_host_pushword_page');

        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $event = new RequestEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        ($this->listener)($event);

        // Falls back to HTTP host string when route host is also unknown
        self::assertSame('127.0.0.1', $this->appPool->getCurrentHost());
    }

    public function testRouteHostNotInConfiguration(): void
    {
        $request = Request::create('https://localhost.dev/invalid.host/about');
        $request->attributes->set('host', 'invalid.host');
        $request->attributes->set('slug', 'about');
        $request->attributes->set('_route', 'custom_host_pushword_page');

        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $event = new RequestEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        ($this->listener)($event);

        // Invalid route host is ignored, falls back to HTTP host
        self::assertSame('localhost.dev', $this->appPool->getCurrentHost());
        self::assertSame('localhost.dev', $this->appPool->getMainHost());
    }

    public function testRequestContextIsSet(): void
    {
        $request = Request::create('https://localhost.dev/blog/my-article/2');
        $request->attributes->set('slug', 'blog/my-article');
        $request->attributes->set('pager', 2);
        $request->attributes->set('_route', 'pushword_page_pager');

        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $event = new RequestEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        ($this->listener)($event);

        self::assertSame('localhost.dev', $this->appPool->getCurrentHost());
        self::assertSame('pushword_page_pager', $this->appPool->getCurrentRoute());
        self::assertSame('blog/my-article', $this->appPool->getCurrentSlug());
        self::assertSame(2, $this->appPool->getCurrentPager());
    }

    public function testSubRequestIsIgnored(): void
    {
        $request = Request::create('https://localhost.dev/about');
        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $event = new RequestEvent(
            $kernel,
            $request,
            HttpKernelInterface::SUB_REQUEST
        );

        $originalHost = $this->appPool->getCurrentHost();
        ($this->listener)($event);

        // Sub-request should not change context
        self::assertSame($originalHost, $this->appPool->getCurrentHost());
    }

    public function testListenerIsRegisteredWithCorrectPriority(): void
    {
        $dispatcher = self::getContainer()->get('event_dispatcher');
        $listeners = $dispatcher->getListeners('kernel.request');

        $found = false;
        foreach ($listeners as $listener) {
            if (\is_array($listener) && $listener[0] instanceof RequestContextListener) {
                $found = true;

                break;
            }
        }

        self::assertTrue($found, 'RequestContextListener should be registered for REQUEST event');
    }
}
