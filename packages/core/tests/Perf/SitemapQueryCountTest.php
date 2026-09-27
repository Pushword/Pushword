<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Perf;

use DateTime;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Cache\PageCacheSuppressor;
use Pushword\Core\Controller\SitemapController;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * sitemap.xml.twig reads every page's translations for its hreflang links. The
 * query count must not grow with the number of listed pages: a linear growth
 * means the collection is lazy-loaded one query per page again.
 */
#[Group('integration')]
final class SitemapQueryCountTest extends KernelTestCase
{
    use QueryCountingTrait;

    private EntityManager $em;

    private SitemapController $sitemapController;

    private PageRepository $pageRepo;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var EntityManager $em */
        $em = $container->get('doctrine.orm.default_entity_manager');
        $this->em = $em;

        $this->sitemapController = $container->get(SitemapController::class);
        $this->pageRepo = $em->getRepository(Page::class);

        $this->startCountingQueries($em->getConnection());
    }

    protected function tearDown(): void
    {
        $this->stopCountingQueries();
        parent::tearDown();
    }

    public function testSitemapQueryCountIsInvariantToPageCount(): void
    {
        $this->resetRepoCaches();
        $baseline = $this->countQueries(fn (): string => $this->renderSitemap());

        $this->em->beginTransaction();

        try {
            $this->seedTranslatedPages(30);
            $this->resetRepoCaches();
            $sitemap = '';
            $scaled = $this->countQueries(function () use (&$sitemap): void {
                $sitemap = $this->renderSitemap();
            });
        } finally {
            $this->em->rollback();
        }

        // Guards against a vacuous pass: the seeded pages and their hreflang
        // links must be in the measured sitemap.
        self::assertStringContainsString('hreflang="fr" href="https://localhost.dev/sitemap-bench-29-fr"', $sitemap);

        self::assertSame(
            $baseline,
            $scaled,
            'sitemap must not issue more queries as the page count grows (N+1 regression)',
        );
    }

    private function renderSitemap(): string
    {
        return (string) $this->sitemapController->show(Request::create('/sitemap.xml'), 'xml')->getContent();
    }

    private function resetRepoCaches(): void
    {
        $this->em->clear();
        $this->pageRepo->onClear();
    }

    private function seedTranslatedPages(int $count): void
    {
        self::getContainer()->get(PageCacheSuppressor::class)->suppress(function () use ($count): void {
            for ($i = 0; $i < $count; ++$i) {
                $en = $this->newPage('sitemap-bench-'.$i, 'en');
                $fr = $this->newPage('sitemap-bench-'.$i.'-fr', 'fr');
                $en->addTranslation($fr);
                $this->em->persist($en);
                $this->em->persist($fr);
            }

            $this->em->flush();
        });
    }

    private function newPage(string $slug, string $locale): Page
    {
        $page = new Page();
        $page->h1 = $slug;
        $page->slug = $slug;
        $page->host = 'localhost.dev';
        $page->locale = $locale;
        $page->createdAt = new DateTime();
        $page->mainContent = 'sitemap bench content';

        return $page;
    }
}
