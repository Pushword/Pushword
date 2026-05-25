<?php

namespace Pushword\Search\Tests;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Search\Event\SearchDocumentEvent;
use Pushword\Search\Service\Indexer;
use Pushword\Search\Service\Searcher;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class SearchIndexTest extends KernelTestCase
{
    private const string HOST = 'localhost.dev';

    public function testIndexAndSearchRoundTrip(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $indexer = $container->get(Indexer::class);
        $searcher = $container->get(Searcher::class);

        $nonce = 'zylphor'.bin2hex(random_bytes(4));
        $page = $this->createPage($nonce);
        $em->persist($page);
        $em->flush();

        $pageId = $page->id;

        try {
            $indexer->reindexHost(self::HOST);

            $results = $searcher->search(self::HOST, $nonce);
            self::assertGreaterThanOrEqual(1, $results['totalHits']);
            self::assertContains($pageId, array_column($results['hits'], 'id'));

            // Incremental removal drops the page out of the index.
            $indexer->removePage($pageId, self::HOST);
            $afterRemoval = $searcher->search(self::HOST, $nonce);
            self::assertNotContains($pageId, array_column($afterRemoval['hits'], 'id'));
        } finally {
            $em->remove($page);
            $em->flush();
        }
    }

    /**
     * The incremental chain end-to-end: a page save/delete reaches the index
     * through the entity listener + Messenger handler, with no manual reindex.
     */
    public function testIncrementalListenerIndexesAndRemovesOnFlush(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $searcher = $container->get(Searcher::class);

        $nonce = 'incnonce'.bin2hex(random_bytes(4));
        $page = $this->createPage($nonce);

        $em->persist($page);
        $em->flush();

        $pageId = $page->id;

        $afterSave = $searcher->search(self::HOST, $nonce);
        self::assertContains($pageId, array_column($afterSave['hits'], 'id'));

        $em->remove($page);
        $em->flush();

        $afterDelete = $searcher->search(self::HOST, $nonce);
        self::assertNotContains($pageId, array_column($afterDelete['hits'], 'id'));
    }

    public function testNoindexPageIsNotIndexed(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $searcher = $container->get(Searcher::class);

        $nonce = 'hidnonce'.bin2hex(random_bytes(4));
        $page = $this->createPage($nonce);
        $page->setMetaRobots('noindex,nofollow');

        $em->persist($page);
        $em->flush();

        $pageId = $page->id;

        try {
            self::assertNotContains($pageId, array_column($searcher->search(self::HOST, $nonce)['hits'], 'id'));
        } finally {
            $em->remove($page);
            $em->flush();
        }
    }

    public function testLocaleFilterNarrowsResults(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $searcher = $container->get(Searcher::class);

        $nonce = 'locnonce'.bin2hex(random_bytes(4));
        $enPage = $this->createPage($nonce, 'en');
        $frPage = $this->createPage($nonce, 'fr');

        $em->persist($enPage);
        $em->persist($frPage);
        $em->flush();

        try {
            $frHits = array_column($searcher->search(self::HOST, $nonce, locale: 'fr')['hits'], 'id');
            self::assertContains($frPage->id, $frHits);
            self::assertNotContains($enPage->id, $frHits);
        } finally {
            $em->remove($enPage);
            $em->remove($frPage);
            $em->flush();
        }
    }

    public function testBlankQueryReturnsNoHits(): void
    {
        self::bootKernel();
        $searcher = self::getContainer()->get(Searcher::class);

        self::assertSame(0, $searcher->search(self::HOST, '   ')['totalHits']);
    }

    /**
     * Extension seam: a listener appends a custom attribute to the document and
     * it round-trips back into the search hit (retrieved via `*`).
     */
    public function testEventListenerContributesCustomAttribute(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $searcher = $container->get(Searcher::class);

        $container->get('event_dispatcher')->addListener(
            SearchDocumentEvent::class,
            static fn (SearchDocumentEvent $event) => $event->setAttribute('productCode', 'FRALP'.$event->getPage()->id),
        );

        $nonce = 'evtnonce'.bin2hex(random_bytes(4));
        $page = $this->createPage($nonce);
        $em->persist($page);
        $em->flush();

        try {
            $container->get(Indexer::class)->reindexHost(self::HOST);
            $hits = $searcher->search(self::HOST, $nonce)['hits'];
            $hit = array_filter($hits, static fn (array $h): bool => $h['id'] === $page->id);

            self::assertSame('FRALP'.$page->id, array_values($hit)[0]['productCode']);
        } finally {
            $em->remove($page);
            $em->flush();
        }
    }

    /**
     * Extension seam: an extra filter narrows results and facets aggregate, both
     * over a filterable attribute (`tags` here, a custom one for a real catalog).
     */
    public function testExtraFilterAndFacetsOverFilterableAttribute(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $searcher = $container->get(Searcher::class);

        $nonce = 'facnonce'.bin2hex(random_bytes(4));
        $hard = $this->createPage($nonce);
        $hard->setTags('hard');

        $easy = $this->createPage($nonce.'b');
        $easy->setTags('easy');
        $easy->setMainContent('A unique marker '.$nonce.' used by the full-text search test.');

        $em->persist($hard);
        $em->persist($easy);
        $em->flush();

        try {
            $container->get(Indexer::class)->reindexHost(self::HOST);

            $filtered = $searcher->search(self::HOST, $nonce, filter: "tags = 'hard'", facets: ['tags']);
            $ids = array_column($filtered['hits'], 'id');

            self::assertContains($hard->id, $ids);
            self::assertNotContains($easy->id, $ids);
            self::assertArrayHasKey('tags', $filtered['facets']);
        } finally {
            $em->remove($hard);
            $em->remove($easy);
            $em->flush();
        }
    }

    private function createPage(string $nonce, string $locale = 'en'): Page
    {
        $page = new Page();
        $page->host = self::HOST;
        $page->locale = $locale;
        $page->setSlug('search-it-'.$locale.'-'.$nonce);
        $page->setH1('Indexable Page');
        $page->setMainContent('A unique marker '.$nonce.' used by the full-text search test.');
        $page->publishedAt = new DateTime('-1 day');

        return $page;
    }
}
