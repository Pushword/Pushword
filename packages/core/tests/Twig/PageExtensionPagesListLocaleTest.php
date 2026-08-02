<?php

namespace Pushword\Core\Tests\Twig;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Twig\PageExtension;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * pages_list() filters on the current page's locale, whatever the search says.
 *
 * A `slug:` term used to switch that filter off, on the assumption that naming a
 * page means wanting that page whatever its language. The assumption did not
 * survive the implementation: the test was `str_contains($search, 'slug:')` over
 * the whole expression, so one `slug:` disabled the locale for every other term
 * ORed with it, and any prefix ending in `slug:` triggered it.
 */
#[Group('integration')]
final class PageExtensionPagesListLocaleTest extends KernelTestCase
{
    private const string HOST = 'localhost.dev';

    private const string EN_SLUG = 'pages-list-locale-en';

    private const string FR_SLUG = 'pages-list-locale-fr';

    private const string CONTEXT_SLUG = 'pages-list-locale-context';

    private const string SHARED_TAG = 'pages-list-locale-fixture';

    private const array ALL_SLUGS = [self::EN_SLUG, self::FR_SLUG, self::CONTEXT_SLUG];

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        self::getContainer()->get(SiteRegistry::class)->switchSite(self::HOST);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        // Same host, same tag, two languages: the only thing that tells them apart is
        // the locale — which is the whole point of the assertions below.
        $this->createPage(self::EN_SLUG, 'en');
        $this->createPage(self::FR_SLUG, 'fr');
        $this->createPage(self::CONTEXT_SLUG, 'en');

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

    private function createPage(string $slug, string $locale): void
    {
        $page = new Page();
        $page->host = self::HOST;
        $page->locale = $locale;
        $page->slug = $slug;
        $page->h1 = 'Locale fixture '.$slug;
        $page->mainContent = 'Locale fixture content.';
        $page->setTags([self::SHARED_TAG]);
        $page->publishedAt = new DateTime('-1 day');

        $this->entityManager->persist($page);
    }

    private function contextPage(): Page
    {
        $page = $this->entityManager->getRepository(Page::class)->findOneBy(['slug' => self::CONTEXT_SLUG]);
        self::assertInstanceOf(Page::class, $page);

        return $page;
    }

    private function render(string $search): string
    {
        return self::getContainer()->get(PageExtension::class)
            ->renderPagesList($search, currentPage: $this->contextPage());
    }

    public function testASlugSearchStaysInTheCurrentLocale(): void
    {
        $rendered = $this->render('slug:'.self::FR_SLUG);

        self::assertStringNotContainsString(self::FR_SLUG, $rendered);
    }

    /** The counterpart: the filter narrows, it does not break naming a page outright. */
    public function testASlugSearchStillFindsAPageOfTheCurrentLocale(): void
    {
        self::assertStringContainsString(self::EN_SLUG, $this->render('slug:'.self::EN_SLUG));
    }

    /**
     * The regression the sniff caused: one `slug:` term disabled the locale for the
     * whole expression, so the tag term next to it reached every language.
     */
    public function testASlugTermDoesNotLeakTheLocaleOntoTheTermsOredWithIt(): void
    {
        $rendered = $this->render('slug:'.self::EN_SLUG.' OR '.self::SHARED_TAG);

        self::assertStringContainsString(self::EN_SLUG, $rendered);
        self::assertStringNotContainsString(self::FR_SLUG, $rendered);
    }

    /** A bare tag search was already locale-filtered; it still is. */
    public function testATagSearchStaysInTheCurrentLocale(): void
    {
        $rendered = $this->render(self::SHARED_TAG);

        self::assertStringContainsString(self::EN_SLUG, $rendered);
        self::assertStringNotContainsString(self::FR_SLUG, $rendered);
    }
}
