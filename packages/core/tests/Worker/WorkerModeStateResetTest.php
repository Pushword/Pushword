<?php

namespace Pushword\Core\Tests\Worker;

use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Cache\PageCacheSuppressor;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Core\EventListener\PageListener;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Router\PushwordRouteGenerator;
use Pushword\Core\Site\RequestContext;
use Pushword\Core\Site\SiteRegistry;
use ReflectionObject;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Worker-mode safety guard. Under PHP-FPM every request is a fresh process, so the
 * in-memory caches the repositories hold (PageRepository slug/redirect maps,
 * MediaRepository filename index) can never go stale. Under a long-running worker
 * (FrankenPHP `worker`, Symfony Runtime) the kernel is reused across requests and
 * only services tagged `kernel.reset` are flushed between them.
 *
 * Each test boots once and replays two requests around the exact reset a worker
 * performs between them — `services_resetter->reset()` — with a write in between.
 * The second request must see the write; if a cache survived stale, a page or
 * media created/renamed in one request would be invisible (wrong 404s, broken
 * image lookups) to the next request served by the same worker.
 */
#[Group('integration')]
#[Group('worker')]
final class WorkerModeStateResetTest extends KernelTestCase
{
    private const string PROBE_SLUG = 'worker-mode-probe-slug';

    private const string PROBE_FILENAME = 'worker-mode-probe.png';

    /** @var list<string> */
    private array $touchedFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->touchedFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        $this->touchedFiles = [];
        parent::tearDown();
    }

    public function testSlugCacheReflectsWritesAcrossSimulatedRequests(): void
    {
        self::bootKernel();

        $host = $this->hostForFixtures();
        $this->removeProbePage($host);

        try {
            // --- Request A: warm the slug cache for this host, then create a page. ---
            self::assertFalse(
                $this->pageRepo()->hasSlug(self::PROBE_SLUG, $host),
                'probe slug must not exist yet (warms the slug cache for the host)',
            );
            $this->createProbePage($host);

            // The slug cache is now warm but stale: it predates the write. This is
            // why a worker MUST reset between requests — and proves the guard below
            // is non-vacuous (the reset is doing real work, not a no-op).
            fwrite(\STDERR, \sprintf(
                "\n[WORKER] slug cache before reset: hasSlug=%s (false = stale, leak the reset must fix)\n",
                $this->pageRepo()->hasSlug(self::PROBE_SLUG, $host) ? 'true' : 'false',
            ));

            // --- Between requests: exactly what a FrankenPHP/Runtime worker runs. ---
            $this->simulateWorkerRequestBoundary();

            // --- Request B: a fresh lookup must reflect the write from request A. ---
            self::assertTrue(
                $this->pageRepo()->hasSlug(self::PROBE_SLUG, $host),
                'worker mode: the slug cache must not serve stale data after a write in a previous request',
            );
        } finally {
            $this->removeProbePage($host);
        }
    }

    public function testFilenameIndexReflectsWritesAcrossSimulatedRequests(): void
    {
        self::bootKernel();

        $this->removeProbeMedia();

        try {
            // --- Request A: warm the filename index (also persists it to cache.app). ---
            self::assertNull(
                $this->mediaRepo()->findOneByFileName(self::PROBE_FILENAME),
                'probe media must not exist yet (warms + persists the filename index)',
            );
            $this->createProbeMedia();

            // --- Between requests: the worker reset. ---
            $this->simulateWorkerRequestBoundary();

            // --- Request B: must resolve the media created in request A. The write
            // bumped the cache.app version counter, so the persisted index can't
            // serve a stale miss. ---
            self::assertNotNull(
                $this->mediaRepo()->findOneByFileName(self::PROBE_FILENAME),
                'worker mode: the filename index must not serve a stale miss after a media write in a previous request',
            );
        } finally {
            $this->removeProbeMedia();
        }
    }

    public function testRequestContextDoesNotLeakCurrentPageAcrossRequests(): void
    {
        self::bootKernel();

        /** @var RequestContext $context */
        $context = self::getContainer()->get(RequestContext::class);

        // --- Request A: a page request sets the current page. ---
        $page = new Page();
        $page->setSlug(self::PROBE_SLUG);

        $context->setCurrentPage($page);
        self::assertSame($page, $context->getCurrentPage());

        // --- The worker boundary. RequestContext is tagged kernel.reset, so this
        // must clear the current page. Without it a later non-page request (404,
        // sitemap, API) that never calls setCurrentPage() would read this stale
        // page — wrong OG image, canonical, breadcrumb — possibly cross-host. ---
        $this->simulateWorkerRequestBoundary();

        self::assertNull(
            $context->getCurrentPage(),
            'worker mode: the current page must not leak into a request that does not set its own',
        );
    }

    public function testSiteRegistryStashDoesNotLeakAcrossRequests(): void
    {
        self::bootKernel();

        /** @var SiteRegistry $registry */
        $registry = self::getContainer()->get(SiteRegistry::class);

        $registry->stash('worker-probe', 'rendered-in-request-A');
        self::assertSame('rendered-in-request-A', $registry->stash('worker-probe'));

        $this->simulateWorkerRequestBoundary();

        self::assertSame(
            '',
            $registry->stash('worker-probe'),
            'worker mode: a stashed fragment must not leak into the next request',
        );
    }

    public function testPageListenerStaticStateDoesNotLeakAcrossRequests(): void
    {
        self::bootKernel();

        // Initialize the listener so the worker runtime tracks it for reset.
        $listener = self::getContainer()->get(PageListener::class);

        // Simulate state orphaned by a request: a slug-change redirect queued in
        // preUpdate whose flush threw before postUpdate could drain it, plus a skip
        // flag a caller left set on a fatal. Both are process-global statics.
        $reflection = new ReflectionObject($listener);
        $pending = $reflection->getProperty('pendingRedirects');
        $pending->setValue(null, [['oldSlug' => 'old', 'newSlug' => 'new', 'host' => 'localhost.dev']]);

        PageListener::$skipSlugChangeDetection = true;

        // --- The worker boundary. ---
        $this->simulateWorkerRequestBoundary();

        self::assertSame(
            [],
            $pending->getValue(),
            'worker mode: an orphaned pending redirect must not replay in a later request',
        );
        self::assertFalse(
            PageListener::$skipSlugChangeDetection,
            'worker mode: a leftover skip-slug-detection flag must not disable redirects globally',
        );
    }

    public function testRouteGeneratorCustomHostPathDoesNotLeakAcrossRequests(): void
    {
        self::bootKernel();

        /** @var PushwordRouteGenerator $router */
        $router = self::getContainer()->get(PushwordRouteGenerator::class);

        // --- Request A: a synchronous static regeneration (triggered by a save on a
        // static host) constructs AbstractGenerator, which flips this shared flag to
        // false so links render without the /{host}/ prefix, like the static site. ---
        $router->setUseCustomHostPath(false);
        self::assertFalse($router->mayUseCustomPath('a-custom-host.dev'));

        // --- The worker boundary. Without kernel.reset the flag stays false and every
        // later request served by this worker loses its /{host}/ prefix. ---
        $this->simulateWorkerRequestBoundary();

        self::assertTrue(
            $router->mayUseCustomPath('a-custom-host.dev'),
            'worker mode: a static regeneration must not leave links stripped of their /{host}/ prefix for the next request',
        );
    }

    /**
     * Reset every service the worker runtime resets between requests
     * (everything tagged `kernel.reset`, via the framework's services_resetter).
     */
    private function simulateWorkerRequestBoundary(): void
    {
        self::getContainer()->get('services_resetter')->reset();
    }

    private function pageRepo(): PageRepository
    {
        return $this->em()->getRepository(Page::class);
    }

    private function mediaRepo(): MediaRepository
    {
        return $this->em()->getRepository(Media::class);
    }

    private function em(): EntityManagerInterface
    {
        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        return $em;
    }

    private function hostForFixtures(): string
    {
        $homepage = $this->pageRepo()->findOneBy(['slug' => 'homepage']);
        self::assertNotNull($homepage, 'fixture page with slug=homepage is required');

        return $homepage->host;
    }

    private function createProbePage(string $host): void
    {
        self::getContainer()->get(PageCacheSuppressor::class)->suppress(function () use ($host): void {
            $em = $this->em();
            $page = new Page();
            $page->setH1('Worker mode probe');
            $page->setSlug(self::PROBE_SLUG);
            $page->host = $host;
            $page->locale = 'en';
            $page->createdAt = new DateTime();
            $page->updatedAt = new DateTime();
            $page->setMainContent('worker mode probe content');

            $em->persist($page);
            $em->flush();
        });
    }

    private function removeProbePage(string $host): void
    {
        $em = $this->em();
        $existing = $em->getRepository(Page::class)->findOneBy(['slug' => self::PROBE_SLUG, 'host' => $host]);
        if (null !== $existing) {
            $em->remove($existing);
            $em->flush();
        }
    }

    private function createProbeMedia(): void
    {
        $this->touchMediaFile(self::PROBE_FILENAME);

        $em = $this->em();
        $media = new Media()
            ->setProjectDir(sys_get_temp_dir())
            ->setStoreIn(sys_get_temp_dir())
            ->setMimeType('image/png')
            ->setSize(1)
            ->setDimensions([10, 10])
            ->setFileName(self::PROBE_FILENAME)
            ->setAlt('worker mode probe');
        $em->persist($media);
        $em->flush();
    }

    private function removeProbeMedia(): void
    {
        $em = $this->em();
        $existing = $em->getRepository(Media::class)->findOneBy(['fileName' => self::PROBE_FILENAME]);
        if (null !== $existing) {
            $em->remove($existing);
            $em->flush();
        }
    }

    /**
     * Create an empty file where MediaHashListener::prePersist() will sha1_file()
     * it on flush. Registered for cleanup in tearDown().
     */
    private function touchMediaFile(string $fileName): void
    {
        $mediaDir = (string) getenv('PUSHWORD_TEST_MEDIA_DIR');
        if ('' === $mediaDir) {
            $mediaDir = \dirname(__DIR__, 3).'/skeleton/media';
        }

        $path = $mediaDir.'/'.$fileName;
        if (! is_file($path)) {
            file_put_contents($path, '');
            $this->touchedFiles[] = $path;
        }
    }
}
