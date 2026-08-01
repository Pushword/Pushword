<?php

namespace Pushword\StaticGenerator\Tests\Cache;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Cache\PageCacheSuppressor;
use Pushword\Core\Event\RenderEpochBumpedEvent;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Template\TemplateResolver;
use Pushword\StaticGenerator\Cache\HostSweepDispatcher;
use Pushword\StaticGenerator\Cache\Message\HostCacheRefreshMessage;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Twig\Environment as Twig;
use Twig\Loader\FilesystemLoader;

final class HostSweepDispatcherTest extends TestCase
{
    /** @var MessageBusInterface&MockObject */
    private MockObject $bus;

    private PageCacheSuppressor $suppressor;

    private HostSweepDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->suppressor = new PageCacheSuppressor();
        $this->dispatcher = new HostSweepDispatcher($this->makeRegistry(), $this->bus, $this->suppressor);
    }

    public function testBumpedCacheModeHostIsSweptOnFlushWithDelay(): void
    {
        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with(
                self::callback(static fn (HostCacheRefreshMessage $message): bool => 'cached.host' === $message->host),
                self::callback(static fn (array $stamps): bool => 1 === \count($stamps) && $stamps[0] instanceof DelayStamp),
            )
            ->willReturn(new Envelope(new HostCacheRefreshMessage('cached.host')));

        $this->dispatcher->onEpochBumped(new RenderEpochBumpedEvent(['cached.host']));
        $this->dispatcher->flush();
    }

    public function testNonCacheModeAndUnknownHostsAreIgnored(): void
    {
        $this->bus->expects($this->never())->method('dispatch');

        $this->dispatcher->onEpochBumped(new RenderEpochBumpedEvent(['plain.host', 'unknown.host']));
        $this->dispatcher->flush();
    }

    public function testDuplicateBumpsCoalesceIntoOneMessage(): void
    {
        $this->bus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new HostCacheRefreshMessage('cached.host')));

        $this->dispatcher->onEpochBumped(new RenderEpochBumpedEvent(['cached.host']));
        $this->dispatcher->onEpochBumped(new RenderEpochBumpedEvent(['cached.host']));
        $this->dispatcher->flush();
    }

    public function testFlushEmptiesTheQueue(): void
    {
        $this->bus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new HostCacheRefreshMessage('cached.host')));

        $this->dispatcher->onEpochBumped(new RenderEpochBumpedEvent(['cached.host']));
        $this->dispatcher->flush();
        $this->dispatcher->flush();
    }

    public function testSuppressedBumpQueuesNothing(): void
    {
        $this->bus->expects($this->never())->method('dispatch');

        $this->suppressor->suppress(function (): void {
            $this->dispatcher->onEpochBumped(new RenderEpochBumpedEvent(['cached.host']));
        });
        $this->dispatcher->flush();
    }

    public function testResetDropsPendingHosts(): void
    {
        $this->bus->expects($this->never())->method('dispatch');

        $this->dispatcher->onEpochBumped(new RenderEpochBumpedEvent(['cached.host']));
        $this->dispatcher->reset();
        $this->dispatcher->flush();
    }

    private function makeRegistry(): SiteRegistry
    {
        $baseConfig = [
            'name' => 'Test',
            'locale' => 'en',
            'locales' => 'en',
            'template' => '@Pushword',
            'entity_can_override_filters' => false,
        ];

        return new SiteRegistry(
            [
                'cached.host' => ['hosts' => ['cached.host'], 'base_url' => 'https://cached.host', 'cache' => 'static'] + $baseConfig,
                'plain.host' => ['hosts' => ['plain.host'], 'base_url' => 'https://plain.host', 'cache' => 'none'] + $baseConfig,
            ],
            new TemplateResolver(new Twig(new FilesystemLoader()), new ArrayAdapter()),
            new ParameterBag(['kernel.project_dir' => sys_get_temp_dir()]),
        );
    }
}
