<?php

namespace Pushword\Core\Tests\Cache;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Cache\RenderEpoch;
use Pushword\Core\Event\RenderEpochBumpedEvent;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Template\TemplateResolver;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment as Twig;
use Twig\Loader\FilesystemLoader;

final class RenderEpochTest extends TestCase
{
    private string $storageDir = '';

    private EventDispatcher $eventDispatcher;

    /** @var RenderEpochBumpedEvent[] */
    private array $dispatchedEvents = [];

    protected function setUp(): void
    {
        $this->storageDir = sys_get_temp_dir().'/pw-render-epoch-test-'.getmypid().'-'.uniqid();
        $this->eventDispatcher = new EventDispatcher();
        $this->dispatchedEvents = [];
        $this->eventDispatcher->addListener(RenderEpochBumpedEvent::class, function (RenderEpochBumpedEvent $event): void {
            $this->dispatchedEvents[] = $event;
        });
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->storageDir);
    }

    public function testGetPersistsOnMissAndStaysStable(): void
    {
        $renderEpoch = $this->makeRenderEpoch();

        $epoch = $renderEpoch->get('example.tld');
        self::assertNotSame('', $epoch);

        // Every subsequent read converges on the persisted value — including a
        // separate instance, standing in for another PHP process (web vs CLI,
        // parent vs generation worker). Otherwise a sweep never converges.
        self::assertSame($epoch, $renderEpoch->get('example.tld'));
        self::assertSame($epoch, $this->makeRenderEpoch()->get('example.tld'));
    }

    public function testGetResolvesAliasToMainHost(): void
    {
        $renderEpoch = $this->makeRenderEpoch();

        self::assertSame($renderEpoch->get('example.tld'), $renderEpoch->get('www.example.tld'));
    }

    public function testBumpChangesTokenAndDispatchesEvent(): void
    {
        $renderEpoch = $this->makeRenderEpoch();
        $before = $renderEpoch->get('example.tld');

        $renderEpoch->bump('example.tld');

        self::assertNotSame($before, $renderEpoch->get('example.tld'));
        self::assertCount(1, $this->dispatchedEvents);
        self::assertSame(['example.tld'], $this->dispatchedEvents[0]->hosts);
    }

    public function testBumpIsVisibleToOtherInstances(): void
    {
        // Web process bumps, CLI cron sweeps: two containers, one storage.
        $renderEpoch = $this->makeRenderEpoch();
        $before = $renderEpoch->get('example.tld');

        $this->makeRenderEpoch()->bump('example.tld');

        self::assertNotSame($before, $renderEpoch->get('example.tld'));
    }

    public function testBumpNullBumpsEveryApp(): void
    {
        $renderEpoch = $this->makeRenderEpoch();
        $first = $renderEpoch->get('example.tld');
        $second = $renderEpoch->get('other.tld');

        $renderEpoch->bump();

        self::assertNotSame($first, $renderEpoch->get('example.tld'));
        self::assertNotSame($second, $renderEpoch->get('other.tld'));
        self::assertCount(1, $this->dispatchedEvents);
        self::assertSame(['example.tld', 'other.tld'], $this->dispatchedEvents[0]->hosts);
    }

    public function testWipedStorageReadsAsNewEpoch(): void
    {
        $renderEpoch = $this->makeRenderEpoch();
        $before = $renderEpoch->get('example.tld');

        // cache:clear removes the storage dir: the random token guarantees the
        // replacement can never collide with what generated pages were stamped with.
        new Filesystem()->remove($this->storageDir);

        self::assertNotSame($before, $this->makeRenderEpoch()->get('example.tld'));
    }

    private function makeRenderEpoch(): RenderEpoch
    {
        return new RenderEpoch($this->storageDir, $this->makeRegistry(), $this->eventDispatcher);
    }

    private function makeRegistry(): SiteRegistry
    {
        $baseConfig = [
            'base_url' => 'https://example.tld',
            'name' => 'Test',
            'locale' => 'en',
            'locales' => 'en',
            'template' => '@Pushword',
            'entity_can_override_filters' => false,
        ];

        return new SiteRegistry(
            [
                'example.tld' => $baseConfig + ['hosts' => ['example.tld', 'www.example.tld']],
                'other.tld' => ['base_url' => 'https://other.tld', 'hosts' => ['other.tld']] + $baseConfig,
            ],
            new TemplateResolver(new Twig(new FilesystemLoader()), new ArrayAdapter()),
            new ParameterBag(['kernel.project_dir' => sys_get_temp_dir()]),
        );
    }
}
