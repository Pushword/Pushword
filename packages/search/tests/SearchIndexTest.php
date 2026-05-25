<?php

namespace Pushword\Search\Tests;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
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
