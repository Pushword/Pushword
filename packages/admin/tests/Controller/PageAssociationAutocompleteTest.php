<?php

declare(strict_types=1);

namespace Pushword\Admin\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\FormField\AutocompleteSubjectConfigurator;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Entity\Page;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;

/**
 * The page form fetches its association candidates through EasyAdmin's autocomplete
 * endpoint. That endpoint rebuilds the fields against an empty subject, so every
 * filter these fields apply has to survive being re-derived there — otherwise the
 * dropdown silently widens to the wrong host, or to the page being edited itself.
 */
#[Group('integration')]
final class PageAssociationAutocompleteTest extends AbstractAdminTestClass
{
    private const string SECOND_HOST = 'admin-block-editor.test';

    public function testParentPageCandidatesStayOnTheEditedPageHost(): void
    {
        $client = $this->loginUser();
        $page = $this->pageOnSecondHost();

        $results = $this->autocomplete($client, $page, 'parentPage', pageSize: 50);

        // The empty subject falls back to the *first* configured host, so a page on
        // any other one is what tells a re-derived filter from a lucky default.
        self::assertNotEmpty($results);
        foreach ($this->parentPagePaths($results) as $path) {
            self::assertStringStartsWith(self::SECOND_HOST.'/', $path);
        }

        $labels = $this->parentPagePaths($results);
        // The homepage is displayed as '/', but candidates are ordered by stored slug.
        $labels = array_map(static fn (string $path): string => str_ends_with($path, '/') ? $path.'homepage' : $path, $labels);

        $sortedLabels = $labels;
        sort($sortedLabels);
        self::assertSame($sortedLabels, $labels, 'without a search, parent pages should be ordered by path');
    }

    public function testParentPageCandidatesExcludeTheEditedPage(): void
    {
        $client = $this->loginUser();
        $page = $this->pageOnSecondHost();

        $ids = array_column($this->autocomplete($client, $page, 'parentPage'), 'entityId');

        self::assertNotContains((string) $page->id, $ids, 'a page cannot be its own parent');
    }

    public function testParentPageSearchWithNoMatchReturnsNoCandidates(): void
    {
        $client = $this->loginUser();
        $page = $this->pageOnSecondHost();

        self::assertSame([], $this->autocomplete($client, $page, 'parentPage', 'unmatched-parent-search-4897'));
    }

    #[DataProvider('parentPageSearchQueries')]
    public function testParentPageSearchRanksExactSlugBeforePaginatedChildren(string $query): void
    {
        $client = $this->loginUser();
        $page = $this->pageOnSecondHost();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $slugs = [];
        for ($i = 0; $i < 15; ++$i) {
            $slugs[] = sprintf('parent-search-blog/article-%02d', $i);
        }

        $slugs = [...$slugs, 'article-parent-search-blog', 'parent-search-blog', 'parent-search-title-only', 'parent-search-body-only'];
        $candidates = [];

        foreach ($slugs as $slug) {
            $candidate = new Page();
            $candidate->host = self::SECOND_HOST;
            $candidate->slug = $slug;
            $candidate->h1 = 'parent-search-title-only' === $slug ? 'Parent-search-blog overview' : 'Parent page candidate';
            if ('parent-search-blog' === $slug) {
                $candidate->h1 = '<b>Blog</b> & travel';
            }

            $candidate->mainContent = 'parent-search-blog';
            $em->persist($candidate);
            $candidates[] = $candidate;
        }

        $em->flush();
        $ids = array_map(static fn (Page $candidate): int => (int) $candidate->id, $candidates);

        try {
            $results = $this->autocomplete($client, $page, 'parentPage', $query);
            $labels = $this->parentPagePaths($results);

            self::assertSame('Blog & travel', new Crawler($results[0]['entityAsString'])->filter('.pw-page-choice__title')->text());
            self::assertStringNotContainsString('<b>', $results[0]['entityAsString']);
            self::assertStringContainsString('&amp;', $results[0]['entityAsString']);

            self::assertCount(10, $results, 'the exact page must be ranked before pagination, even with many matching articles');
            self::assertSame(self::SECOND_HOST.'/parent-search-blog', $labels[0]);
            self::assertSame(self::SECOND_HOST.'/parent-search-blog/article-00', $labels[1]);

            $allResults = $this->autocomplete($client, $page, 'parentPage', $query, 50);
            $allLabels = $this->parentPagePaths($allResults);
            self::assertCount(18, $allResults);
            self::assertSame(self::SECOND_HOST.'/parent-search-blog/article-14', $allLabels[15]);
            self::assertContains(self::SECOND_HOST.'/parent-search-title-only', $allLabels);
            self::assertNotContains(self::SECOND_HOST.'/parent-search-body-only', $allLabels);
        } finally {
            $em = self::getContainer()->get(EntityManagerInterface::class);
            foreach ($ids as $id) {
                $candidate = $em->find(Page::class, $id);
                if (null !== $candidate) {
                    $em->remove($candidate);
                }
            }

            $em->flush();
        }
    }

    /** @return iterable<string, array{string}> */
    public static function parentPageSearchQueries(): iterable
    {
        yield 'slug' => ['parent-search-blog'];
        yield 'path' => ['/parent-search-blog/'];
        yield 'host and path' => [self::SECOND_HOST.'/parent-search-blog'];
        yield 'full URL' => ['https://'.self::SECOND_HOST.'/parent-search-blog'];
        yield 'HTTP URL' => ['http://'.self::SECOND_HOST.'/parent-search-blog'];
        yield 'case and whitespace' => [' PARENT-SEARCH-BLOG '];
    }

    public function testVariantOfCandidatesOfferOnlyMasters(): void
    {
        $client = $this->loginUser();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $page = $this->pageOnSecondHost();

        $ids = array_column($this->autocomplete($client, $page, 'variantOf'), 'entityId');
        self::assertNotContains((string) $page->id, $ids);

        foreach ($ids as $id) {
            $candidate = $em->getRepository(Page::class)->find((int) $id);
            self::assertNotNull($candidate);
            self::assertNull($candidate->variantOf, 'only a master can be a variant master');
        }
    }

    public function testTranslationCandidatesExcludeTheEditedLocale(): void
    {
        $client = $this->loginUser();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $page = $this->pageOnSecondHost();

        $ids = array_column($this->autocomplete($client, $page, 'translations'), 'entityId');
        self::assertNotContains((string) $page->id, $ids);

        foreach ($ids as $id) {
            $candidate = $em->getRepository(Page::class)->find((int) $id);
            self::assertNotNull($candidate);
            self::assertNotSame($page->locale, $candidate->locale);
        }
    }

    /**
     * Deliberately unlike the two fields above: a multilingual site may serve each
     * locale from its own domain, so a translation is looked for across hosts.
     */
    public function testTranslationCandidatesCrossHosts(): void
    {
        $client = $this->loginUser();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $page = $this->pageOnSecondHost();

        $foreign = new Page();
        $foreign->h1 = 'Autocomplete cross-host translation';
        $foreign->slug = 'autocomplete-cross-host-translation';
        $foreign->locale = 'de';
        $foreign->host = 'localhost.dev';

        $em->persist($foreign);
        $em->flush();

        $foreignId = (int) $foreign->id;

        try {
            $ids = array_column($this->autocomplete($client, $page, 'translations'), 'entityId');
            self::assertContains((string) $foreignId, $ids);
        } finally {
            // The request above cleared the identity map, so the instance held here
            // is detached and only a fresh one can be removed.
            $em = self::getContainer()->get(EntityManagerInterface::class);
            $stored = $em->getRepository(Page::class)->find($foreignId);
            if (null !== $stored) {
                $em->remove($stored);
                $em->flush();
            }
        }
    }

    /**
     * A page being created has no id for the endpoint to name, so nothing is passed
     * and both requests fall back the same way — on the host the page will actually
     * be created on, which `createEntity()` takes from the site registry.
     */
    public function testCandidatesForANewPageMatchTheHostItWillBeCreatedOn(): void
    {
        $client = $this->loginUser();

        $crawler = $client->request(Request::METHOD_GET, '/admin/page/new');
        self::assertResponseIsSuccessful();

        $host = $crawler->filter('select[name="Page[host]"] option[selected]')->attr('value');
        self::assertIsString($host, 'the new page form must say which host it creates on');

        $endpoint = $crawler->filter('[name^="Page[parentPage]"][data-ea-autocomplete-endpoint-url]')
            ->attr('data-ea-autocomplete-endpoint-url');
        self::assertIsString($endpoint);
        self::assertStringNotContainsString(AutocompleteSubjectConfigurator::SUBJECT_ID_PARAM, $endpoint);

        $results = $this->fetchResults($client, $endpoint);

        self::assertNotEmpty($results);
        foreach ($this->parentPagePaths($results) as $path) {
            self::assertStringStartsWith($host.'/', $path);
        }
    }

    /**
     * @param list<array{entityId: string, entityAsString: string}> $results
     *
     * @return list<string>
     */
    private function parentPagePaths(array $results): array
    {
        return array_map(static fn (array $result): string => new Crawler($result['entityAsString'])->filter('.pw-page-choice__path')->text(), $results);
    }

    private function pageOnSecondHost(): Page
    {
        $page = self::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(Page::class)
            ->findOneBy(['host' => self::SECOND_HOST, 'slug' => 'kitchen-sink']);

        self::assertInstanceOf(Page::class, $page);

        return $page;
    }

    /**
     * @return list<array{entityId: string, entityAsString: string}>
     */
    private function autocomplete(KernelBrowser $client, Page $page, string $property, string $query = '', int $pageSize = 10): array
    {
        $crawler = $client->request(Request::METHOD_GET, '/admin/page/'.$page->id.'/edit');
        self::assertResponseIsSuccessful();

        $endpoint = $crawler->filter(sprintf('[name^="Page[%s]"][data-ea-autocomplete-endpoint-url]', $property))
            ->attr('data-ea-autocomplete-endpoint-url');
        self::assertIsString($endpoint, $property.' must be fetched on demand');
        self::assertStringContainsString('pwSubjectId='.$page->id, $endpoint, 'the endpoint must name the edited page');

        $endpoint .= '&'.http_build_query(['query' => $query, 'pageSize' => $pageSize]);

        return $this->fetchResults($client, $endpoint);
    }

    /**
     * @return list<array{entityId: string, entityAsString: string}>
     */
    private function fetchResults(KernelBrowser $client, string $endpoint): array
    {
        $client->request(Request::METHOD_GET, $endpoint);
        self::assertResponseIsSuccessful();

        /** @var array{results: list<array{entityId: string, entityAsString: string}>} $payload */
        $payload = json_decode($client->getInternalResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $payload['results'];
    }
}
