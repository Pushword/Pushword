<?php

namespace Pushword\Core\Tests\Twig;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Component\EntityFilter\Filter\LinkCollector;
use Pushword\Core\Component\EntityFilter\ManagerPool;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Service\LinkCollectorService;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Twig\PageExtension;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * pages_list(excludeAlreadyLinked: true) must dedupe against what previous lists rendered,
 * not only against the links the LinkCollector filter read from the raw prose. A hub page
 * carrying several lists used to render the same card in each of them.
 */
#[Group('integration')]
final class PageExtensionPagesListExcludeLinkedTest extends KernelTestCase
{
    private const string HOST = 'localhost.dev';

    private const string ALPHA_SLUG = 'pages-list-dedupe-alpha';

    private const string BRAVO_SLUG = 'pages-list-dedupe-bravo';

    private const string CHARLIE_SLUG = 'pages-list-dedupe-charlie';

    private const array ALL_SLUGS = [self::ALPHA_SLUG, self::BRAVO_SLUG, self::CHARLIE_SLUG];

    /** A slug: search keeps the locale filter out of the way and matches every fixture. */
    private const string SEARCH = 'slug:'.self::ALPHA_SLUG
        .' OR slug:'.self::BRAVO_SLUG
        .' OR slug:'.self::CHARLIE_SLUG;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        self::getContainer()->get(SiteRegistry::class)->switchSite(self::HOST);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        // The collector lives for a request; nothing resets it between two kernel tests.
        self::getContainer()->get(LinkCollectorService::class)->reset();

        foreach (self::ALL_SLUGS as $slug) {
            $this->createPage($slug);
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

    private function createPage(string $slug): void
    {
        $page = new Page();
        $page->host = self::HOST;
        $page->locale = 'en';
        $page->setSlug($slug);
        $page->setH1('Dedupe fixture '.$slug);
        $page->setMainContent('Dedupe fixture content.');
        $page->setPublishedAt(new DateTime('-1 day'));

        $this->entityManager->persist($page);
    }

    private function ext(): PageExtension
    {
        return self::getContainer()->get(PageExtension::class);
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
     * The feature the collector exists for, end to end: the filter reads the prose before
     * Twig runs, the list then skips what the prose already links. LinkCollectorTest covers
     * the extraction itself; what matters here is that a list honours it — the two halves
     * only meet through the shared LinkCollectorService, so nothing else pins them together.
     */
    public function testListExcludesASlugLinkedInThePageProse(): void
    {
        $context = new Page();
        $context->host = self::HOST;
        $context->locale = 'en';
        $context->setSlug('pages-list-dedupe-context');

        new LinkCollector(
            self::getContainer()->get(LinkCollectorService::class),
            self::getContainer()->get(PageRepository::class),
        )->apply(
            'Read [alpha](/'.self::ALPHA_SLUG.') first.',
            $context,
            self::getContainer()->get(ManagerPool::class)->getManager($context),
        );

        self::assertSame(
            [self::BRAVO_SLUG, self::CHARLIE_SLUG],
            $this->slugsIn($this->ext()->renderPagesList(self::SEARCH, excludeAlreadyLinked: true)),
        );
    }

    /**
     * The regression: a page carrying a narrow list then a broad one rendered the narrow
     * list's cards twice. The broad list must now skip what the first one displayed.
     */
    public function testSecondListExcludesWhatTheFirstOneRendered(): void
    {
        $first = $this->slugsIn($this->ext()->renderPagesList('slug:'.self::ALPHA_SLUG, excludeAlreadyLinked: true));
        $second = $this->slugsIn($this->ext()->renderPagesList(self::SEARCH, excludeAlreadyLinked: true));

        self::assertSame([self::ALPHA_SLUG], $first);
        self::assertSame([self::BRAVO_SLUG, self::CHARLIE_SLUG], $second, 'A page rendered by the first list came back in the second.');
    }

    /**
     * An excluding list must still render `max` cards: the exclusion happens in SQL, so the
     * limit counts the pages that survived it. Filtering the hydrated result instead left
     * this list one card short — it fetched alpha+bravo, then dropped alpha.
     */
    public function testExcludingListStillFillsItsMax(): void
    {
        $this->ext()->renderPagesList('slug:'.self::ALPHA_SLUG, excludeAlreadyLinked: true);

        self::assertSame(
            [self::BRAVO_SLUG, self::CHARLIE_SLUG],
            $this->slugsIn($this->ext()->renderPagesList(self::SEARCH, 2, excludeAlreadyLinked: true)),
        );
    }

    /** Exhausting the pool leaves the next list empty rather than repeating the same cards. */
    public function testThirdListIsEmptyOnceEveryPageHasBeenRendered(): void
    {
        self::assertSame(self::ALL_SLUGS, $this->slugsIn($this->ext()->renderPagesList(self::SEARCH, excludeAlreadyLinked: true)));

        self::assertSame([], $this->slugsIn($this->ext()->renderPagesList(self::SEARCH, excludeAlreadyLinked: true)));
    }

    /**
     * Registering stays opt-in: a plain list must not silently empty a later excluding one,
     * which would break pages mixing a full listing with a "related" carousel.
     */
    public function testListWithoutTheFlagDoesNotRegisterWhatItRenders(): void
    {
        $this->ext()->renderPagesList(self::SEARCH);

        self::assertSame(self::ALL_SLUGS, $this->slugsIn($this->ext()->renderPagesList(self::SEARCH, excludeAlreadyLinked: true)));
    }

    /** A paginated list only renders its current page results — the rest stays available. */
    public function testPaginatedListRegistersOnlyItsRenderedPage(): void
    {
        $paginated = $this->slugsIn($this->ext()->renderPagesList(self::SEARCH, 1, maxPages: 3, excludeAlreadyLinked: true));
        self::assertCount(1, $paginated);

        $next = $this->slugsIn($this->ext()->renderPagesList(self::SEARCH, 3, excludeAlreadyLinked: true));
        self::assertSame([], array_intersect($paginated, $next));
        self::assertCount(2, $next, 'Pager pages that were never rendered must stay listable.');
    }
}
