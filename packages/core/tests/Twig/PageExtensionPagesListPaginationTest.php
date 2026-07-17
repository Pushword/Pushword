<?php

namespace Pushword\Core\Tests\Twig;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Site\RequestContext;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Twig\PageExtension;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * pages_list() paginating with maxPages must reach every pager page: "max" is the number
 * of cards per pager page, so the query has to fetch max * maxPages pages, not max.
 */
#[Group('integration')]
final class PageExtensionPagesListPaginationTest extends KernelTestCase
{
    private const string HOST = 'localhost.dev';

    private const string ALPHA_SLUG = 'pages-list-pager-alpha';

    private const string BRAVO_SLUG = 'pages-list-pager-bravo';

    private const string CHARLIE_SLUG = 'pages-list-pager-charlie';

    private const array ALL_SLUGS = [self::ALPHA_SLUG, self::BRAVO_SLUG, self::CHARLIE_SLUG];

    private const string SEARCH = 'slug:'.self::ALPHA_SLUG
        .' OR slug:'.self::BRAVO_SLUG
        .' OR slug:'.self::CHARLIE_SLUG;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        self::getContainer()->get(SiteRegistry::class)->switchSite(self::HOST);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        // Distinct publication dates: the default publishedAt order is then total, so each
        // pager page holds one known fixture instead of an arbitrary one.
        foreach (self::ALL_SLUGS as $index => $slug) {
            $this->createPage($slug, new DateTime('-'.($index + 1).' day'));
        }

        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        foreach (self::ALL_SLUGS as $slug) {
            foreach ($this->entityManager->getRepository(Page::class)->findBy(['slug' => $slug]) as $page) {
                $this->entityManager->remove($page);
            }
        }

        $this->entityManager->flush();

        parent::tearDown();
    }

    private function createPage(string $slug, DateTime $publishedAt): void
    {
        $page = new Page();
        $page->host = self::HOST;
        $page->locale = 'en';
        $page->setSlug($slug);
        $page->setH1('Pager fixture '.$slug);
        $page->setMainContent('Pager fixture content.');
        $page->setPublishedAt($publishedAt);

        $this->entityManager->persist($page);
    }

    private function ext(): PageExtension
    {
        return self::getContainer()->get(PageExtension::class);
    }

    /** Puts the renderer on pager page $pager, as the {pager} route parameter does in a request. */
    private function onPager(int $pager): void
    {
        self::getContainer()->get(RequestContext::class)
            ->setRequestContext(self::HOST, 'pushword_page', '', $pager);
    }

    /**
     * @return string[] the fixture slugs rendered in $html
     */
    private function slugsIn(string $html): array
    {
        return array_values(array_filter(
            self::ALL_SLUGS,
            static fn (string $slug): bool => str_contains($html, $slug),
        ));
    }

    /**
     * The regression: only "max" pages were fetched, so pager pages beyond the first had
     * nothing left to slice and rendered empty.
     */
    public function testEachPagerPageRendersItsOwnCard(): void
    {
        $rendered = [];
        foreach ([1, 2, 3] as $pager) {
            $this->onPager($pager);
            $rendered[$pager] = $this->slugsIn($this->ext()->renderPagesList(self::SEARCH, 1, maxPages: 3));
            self::assertCount(1, $rendered[$pager], 'Pager page '.$pager.' rendered no card.');
        }

        self::assertSame(
            self::ALL_SLUGS,
            array_merge(...array_values($rendered)),
            'Each pager page must render the next fixture, in publishedAt order.',
        );
    }

    /** Same expectation through the legacy array form: max: [perPage, maxPages]. */
    public function testLegacyArrayMaxReachesEveryPagerPage(): void
    {
        $this->onPager(2);

        self::assertSame([self::BRAVO_SLUG], $this->slugsIn($this->ext()->renderPagesList(self::SEARCH, [1, 3])));
    }

    /** maxPages caps the corpus: a 4th page must not be reachable when maxPages is 3. */
    public function testMaxPagesStillCapsTheNumberOfFetchedPages(): void
    {
        $this->onPager(1);

        // 3 fixtures, 2 per pager page, maxPages 1 => only the first 2 are ever listable.
        self::assertSame(
            [self::ALPHA_SLUG, self::BRAVO_SLUG],
            $this->slugsIn($this->ext()->renderPagesList(self::SEARCH, 2, maxPages: 1)),
        );
    }
}
