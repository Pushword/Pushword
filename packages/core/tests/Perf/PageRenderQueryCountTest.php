<?php

namespace Pushword\Core\Tests\Perf;

use DateTime;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Cache\PageCacheSuppressor;
use Pushword\Core\Controller\PageController;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Repository\PageRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Structural regression guard for the front-end page render hot path. Renders a
 * link- and media-rich page, then renders it again after scaling the corpus
 * ~100x. The query count must stay flat: internal links resolve through the
 * batched slug cache and media through the filename index, so neither should add
 * a query per link/image/page. A linear growth here means an N+1 was reintroduced
 * somewhere in the render pipeline (a Twig extension, related-pages, media, menu).
 */
#[Group('integration')]
final class PageRenderQueryCountTest extends KernelTestCase
{
    use QueryCountingTrait;

    private const string SLUG = 'kitchen-sink';

    private EntityManager $em;

    private PageController $pageController;

    private PageRepository $pageRepo;

    private MediaRepository $mediaRepo;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var EntityManager $em */
        $em = $container->get('doctrine.orm.default_entity_manager');
        $this->em = $em;

        $this->pageController = $container->get(PageController::class);
        $this->pageRepo = $em->getRepository(Page::class);
        $this->mediaRepo = $em->getRepository(Media::class);

        $this->startCountingQueries($em->getConnection());
    }

    protected function tearDown(): void
    {
        $this->stopCountingQueries();
        parent::tearDown();
    }

    public function testRenderQueryCountIsInvariantToCorpusSize(): void
    {
        // Warm the content render cache once so both measured renders are in the
        // same cache state — otherwise the first render would pay the markdown
        // parse and the second wouldn't, skewing the comparison.
        $this->renderPage();

        $this->resetRepoCaches();
        $baseline = $this->countQueries(fn (): Response => $this->renderPage());

        $this->em->beginTransaction();

        try {
            $this->seedPages($this->hostForFixtures(), 300);
            $this->resetRepoCaches();
            $scaled = $this->countQueries(fn (): Response => $this->renderPage());
        } finally {
            $this->em->rollback();
        }

        fwrite(\STDERR, \sprintf(
            "\n[PERF] page render '%s' query count: %d (baseline) vs %d (after +300 pages)\n",
            self::SLUG,
            $baseline,
            $scaled,
        ));

        self::assertSame(
            $baseline,
            $scaled,
            'page render must not issue more queries as the corpus grows (N+1 regression)',
        );
    }

    private function renderPage(): Response
    {
        $response = $this->pageController->show(Request::create(self::SLUG), self::SLUG);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        return $response;
    }

    private function hostForFixtures(): string
    {
        $homepage = $this->pageRepo->findOneBy(['slug' => 'homepage']);
        self::assertNotNull($homepage, 'fixture page with slug=homepage is required');

        return $homepage->host;
    }

    private function resetRepoCaches(): void
    {
        $this->em->clear();
        $this->pageRepo->onClear();
        $this->mediaRepo->bumpVersion();
    }

    private function seedPages(string $host, int $count): void
    {
        self::getContainer()->get(PageCacheSuppressor::class)->suppress(function () use ($host, $count): void {
            for ($i = 0; $i < $count; ++$i) {
                $page = new Page();
                $page->setH1('Render bench '.$i);
                $page->setSlug('render-bench-'.$i);
                $page->host = $host;
                $page->locale = 'en';
                $page->createdAt = new DateTime();
                $page->setMainContent('render bench content '.$i);
                $this->em->persist($page);
            }

            $this->em->flush();
        });
    }
}
