<?php

namespace Pushword\StaticGenerator\Tests\Cache;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Cache\RenderEpoch;
use Pushword\Core\Entity\Page;
use Pushword\Core\Site\SiteConfig;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Snippet\Entity\Snippet;
use Pushword\StaticGenerator\Cache\HostSweepDispatcher;
use Pushword\StaticGenerator\Cache\Message\HostCacheRefreshMessage;
use Pushword\StaticGenerator\Cache\MessageHandler\HostCacheRefreshHandler;
use Pushword\StaticGenerator\StaticAppGenerator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Full Phase 0+1 loop against the real container: a snippet write bumps the
 * host epoch, the sweep regenerates a page whose rendered output embeds the
 * snippet, and the epoch-equality debounce stops redundant sweeps.
 *
 * The page references the snippet from its title (the default `title` filter
 * chain includes Twig), so its own updatedAt never moves — exactly the
 * staleness class the epoch exists for.
 */
#[Group('integration')]
final class EpochSweepIntegrationTest extends KernelTestCase
{
    private const string HOST = 'localhost.dev';

    private string $cacheDir = '';

    private ?Page $page = null;

    private ?Snippet $snippet = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Restore pristine DB so page fixtures are available for rendering.
        $cacheFile = getenv('PUSHWORD_TEST_DB_CACHE_FILE');
        $dbUrl = getenv('PUSHWORD_TEST_DATABASE_URL');
        if (false !== $cacheFile && '' !== $cacheFile && false !== $dbUrl && file_exists($cacheFile)) {
            $dbPath = preg_replace('#^sqlite:///+#', '/', $dbUrl);
            if (null !== $dbPath && file_exists($dbPath)) {
                copy($cacheFile, $dbPath);
            }
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->cacheDir = $this->getGenerator()->getCacheDir($this->getSiteConfig());
    }

    protected function tearDown(): void
    {
        $em = $this->getEm();
        foreach ([$this->page, $this->snippet] as $entity) {
            if (null !== $entity) {
                $managed = $em->find($entity::class, $entity->id);
                if (null !== $managed) {
                    $em->remove($managed);
                }
            }
        }

        $em->flush();
        new Filesystem()->remove($this->cacheDir);
        parent::tearDown();
    }

    public function testSnippetEditSweepsThePagesThatRenderIt(): void
    {
        $em = $this->getEm();
        $renderEpoch = self::getContainer()->get(RenderEpoch::class);
        $stateManager = $this->getGenerator()->getStateManager();

        $slug = 'epoch-int-'.uniqid();
        $this->snippet = new Snippet();
        $snippet = $this->snippet;
        $snippet->host = self::HOST;
        $snippet->slug = $slug;
        $snippet->name = 'Epoch Integration';
        $snippet->content = 'snippet-v1';

        $this->page = new Page();
        $page = $this->page;
        $page->host = self::HOST;
        $page->slug = 'epoch-int-page';
        $page->title = "Epoch {{ snippet('".$slug."') }}";
        $page->h1 = 'Epoch integration page';
        $page->mainContent = 'Content that never changes.';
        $page->publishedAt = new DateTime('-1 hour');

        $em->persist($snippet);
        $em->persist($page);
        $em->flush();

        // The snippet persist bumped the epoch and queued the host. In test env
        // the message lands on an in-memory transport (mirroring an async
        // install); envelope shape is covered by HostSweepDispatcherTest, here
        // we consume the sweep the way a worker would.
        $dispatcher = self::getContainer()->get(HostSweepDispatcher::class);
        $dispatcher->flush();

        $handler = self::getContainer()->get(HostCacheRefreshHandler::class);
        $handler(new HostCacheRefreshMessage(self::HOST));

        $pageFile = $this->cacheDir.'/epoch-int-page.html';
        self::assertFileExists($pageFile);
        self::assertStringContainsString('snippet-v1', (string) file_get_contents($pageFile));

        $stateManager->reload();
        $currentEpoch = $renderEpoch->get(self::HOST);
        // Any render error aborts the state write (`setError()` sets abortGeneration),
        // so an unrecorded epoch says "something failed to render", not "the epoch was
        // not recorded". Name it, or the next reader debugs the wrong end.
        self::assertSame([], $this->getGenerator()->getErrors(), 'the sweep hit a render error');
        self::assertSame($currentEpoch, $stateManager->getSweptEpoch(self::HOST), 'a completed sweep records the sampled epoch');

        // Edit the snippet: the page row is untouched (updatedAt frozen), yet its
        // rendered output must change — the pre-epoch blind spot.
        $snippet->content = 'snippet-v2';
        $em->flush();
        $dispatcher->flush();
        $handler(new HostCacheRefreshMessage(self::HOST));

        self::assertStringContainsString('snippet-v2', (string) file_get_contents($pageFile));

        // Debounce: with sweptEpoch current, the handler must not sweep again —
        // observable through a poisoned state entry surviving the call.
        $stateManager->reload();
        $stateManager->setPageState(self::HOST, 'epoch-int-page', new DateTimeImmutable('2000-01-01'), 'poisoned');
        $stateManager->save();

        $handler(new HostCacheRefreshMessage(self::HOST));

        $stateManager->reload();
        self::assertTrue(
            $stateManager->needsRegeneration(self::HOST, 'epoch-int-page', new DateTimeImmutable('2000-01-01'), $renderEpoch->get(self::HOST)),
            'the poisoned entry must survive: a current sweptEpoch means the handler no-ops',
        );

        // A new bump reopens the gate: the handler sweeps and heals the entry.
        $renderEpoch->bump(self::HOST);
        $handler(new HostCacheRefreshMessage(self::HOST));

        $stateManager->reload();
        self::assertSame($renderEpoch->get(self::HOST), $stateManager->getSweptEpoch(self::HOST));
        $entryIsFresh = ! $stateManager->needsRegeneration(
            self::HOST,
            'epoch-int-page',
            DateTimeImmutable::createFromInterface($this->requirePageUpdatedAt()),
            $renderEpoch->get(self::HOST),
        );
        self::assertTrue($entryIsFresh, 'the sweep re-stamps the page with the freshly sampled epoch');
    }

    public function testPageWritesDriveTheEpochThroughARealFlush(): void
    {
        $em = $this->getEm();
        $renderEpoch = self::getContainer()->get(RenderEpoch::class);

        $before = $renderEpoch->get(self::HOST);
        $this->page = new Page();
        $page = $this->page;
        $page->host = self::HOST;
        $page->slug = 'epoch-flush-page';
        $page->title = 'Epoch flush page';
        $page->mainContent = 'Prose without links.';

        $em->persist($page);
        $em->flush();

        $afterPersist = $renderEpoch->get(self::HOST);
        self::assertNotSame($before, $afterPersist, 'persisting a published page must bump (it enters listings)');

        // Prose edit touching no link: private to the page, no bump.
        $page->mainContent = 'Rewritten prose, still without links.';
        $em->flush();
        self::assertSame($afterPersist, $renderEpoch->get(self::HOST));

        // Metadata edit: the real PreUpdateEventArgs changeset must carry it.
        $page->title = 'Epoch flush page (renamed)';
        $em->flush();
        $afterTitle = $renderEpoch->get(self::HOST);
        self::assertNotSame($afterPersist, $afterTitle);

        // Content edit adding an internal link: other pages render the link graph.
        $page->mainContent = 'Rewritten prose with a [link](/epoch-int-page).';
        $em->flush();
        self::assertNotSame($afterTitle, $renderEpoch->get(self::HOST));
    }

    private function requirePageUpdatedAt(): DateTimeInterface
    {
        $updatedAt = $this->page?->updatedAt;
        self::assertNotNull($updatedAt);

        return $updatedAt;
    }

    private function getEm(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function getGenerator(): StaticAppGenerator
    {
        return self::getContainer()->get(StaticAppGenerator::class);
    }

    private function getSiteConfig(): SiteConfig
    {
        return self::getContainer()->get(SiteRegistry::class)->switchSite(self::HOST)->get();
    }
}
