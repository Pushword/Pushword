<?php

declare(strict_types=1);

namespace Pushword\Search\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Template\TemplateResolver;
use Pushword\Search\Service\FrenchElision;
use Pushword\Search\Service\IndexManager;
use Pushword\Search\Service\Searcher;

use function Safe\json_encode;

use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment as Twig;
use Twig\Loader\FilesystemLoader;

/**
 * French queries against a real Loupe index, through the same IndexManager and
 * Searcher the site uses.
 */
#[Group('integration')]
final class FrenchSearchTest extends TestCase
{
    private const string HOST = 'fr.test';

    private const string ACOMPTE_TITLE = 'Comment fonctionnent l’acompte et le paiement du solde ?';

    private string $varDir = '';

    private string|false $previousVarDir = false;

    protected function setUp(): void
    {
        // IndexManager takes its directory from this variable under ParaTest.
        $this->varDir = sys_get_temp_dir().'/pw-french-search-'.uniqid();
        $this->previousVarDir = getenv('PUSHWORD_TEST_VAR_DIR');
        putenv('PUSHWORD_TEST_VAR_DIR='.$this->varDir);
    }

    protected function tearDown(): void
    {
        putenv(false === $this->previousVarDir ? 'PUSHWORD_TEST_VAR_DIR' : 'PUSHWORD_TEST_VAR_DIR='.$this->previousVarDir);
        new Filesystem()->remove($this->varDir);
    }

    /** @return iterable<string, array{string}> */
    public static function provideAcompteQueries(): iterable
    {
        yield 'bare word' => ['acompte'];
        yield 'straight apostrophe' => ["l'acompte"];
        yield 'typographic apostrophe' => ['l’acompte'];
        yield 'upper case' => ['L’ACOMPTE'];
        yield 'mixed case, straight apostrophe' => ["L'Acompte"];
    }

    /**
     * The elided title and the bare `un acompte` body are the same word: every
     * spelling of the query finds both, and nothing else.
     */
    #[DataProvider('provideAcompteQueries')]
    public function testEverySpellingFindsTheElidedAndTheBareWord(string $query): void
    {
        $searcher = $this->indexedSearcher();

        self::assertSame([1, 2], $this->ids($searcher->search(self::HOST, $query)));
    }

    /** @return iterable<string, array{string}> */
    public static function provideEteQueries(): iterable
    {
        yield 'accented' => ['été'];
        yield 'unaccented' => ['ete'];
        yield 'elided, upper case' => ['D’ÉTÉ'];
        yield 'elided, straight apostrophe' => ["d'ete"];
    }

    #[DataProvider('provideEteQueries')]
    public function testAccentsAndCaseDoNotMatterBehindAnElision(string $query): void
    {
        self::assertSame([4], $this->ids($this->indexedSearcher()->search(self::HOST, $query)));
    }

    public function testHitsShowTheTextAsWrittenWithTheBareWordHighlighted(): void
    {
        $hits = $this->indexedSearcher()->search(self::HOST, 'acompte')['hits'];
        $hit = $hits[0];

        self::assertSame(1, $hit['id']);
        self::assertSame(self::ACOMPTE_TITLE, $hit['title']);
        self::assertIsArray($hit['_formatted']);
        self::assertSame('Comment fonctionnent l’<em>acompte</em> et le paiement du solde ?', $hit['_formatted']['title']);
        self::assertStringNotContainsString(FrenchElision::BOUNDARY, json_encode($hits));
    }

    /**
     * A filter compares the stored value, so an attribute both searchable and
     * filterable keeps its own.
     */
    public function testFilterableAttributesAreStoredVerbatim(): void
    {
        $siteRegistry = $this->siteRegistry();
        $indexManager = new IndexManager($siteRegistry, $this->varDir, ['title', 'region'], ['host', 'region']);
        $indexManager->replaceAll(self::HOST, [
            ['id' => 1, 'host' => self::HOST, 'title' => 'Gorges', 'region' => 'l’Ardèche'],
            ['id' => 2, 'host' => self::HOST, 'title' => 'Gorges', 'region' => 'Drôme'],
        ]);

        $results = new Searcher($indexManager, $siteRegistry, 20)->search(self::HOST, 'gorges', filter: "region = 'l’Ardèche'");

        self::assertSame([1], $this->ids($results));
    }

    public function testAnIncrementalWriteSplitsElisionsToo(): void
    {
        $indexManager = $this->indexManager($this->siteRegistry());
        $indexManager->replaceAll(self::HOST, [$this->document(3, 'Le matériel de bivouac', 'La liste.')]);
        $indexManager->addDocument(self::HOST, $this->document(1, self::ACOMPTE_TITLE, 'Versez-le à la réservation.'));

        $searcher = new Searcher($indexManager, $this->siteRegistry(), 20);

        self::assertSame([1], $this->ids($searcher->search(self::HOST, 'acompte')));
    }

    /**
     * Without stop words, `de` pulls in a page that shares nothing else with the
     * question. Configured with a capital or an accent, the stop words still
     * apply: Loupe compares them to folded query terms.
     */
    public function testStopWordsKeepAFunctionWordFromMatchingEveryPage(): void
    {
        self::assertSame([1, 2, 3], $this->ids($this->indexedSearcher()->search(self::HOST, 'acompte de')));

        $searcher = $this->indexedSearcher(['search_stop_words' => ['De', 'À']]);

        self::assertSame([1, 2], $this->ids($searcher->search(self::HOST, 'acompte de')));
        self::assertSame([1, 2], $this->ids($searcher->search(self::HOST, 'acompte à')));
    }

    public function testHitsDropTheRawContentAndKeepAReadableSnippet(): void
    {
        $results = $this->indexedSearcher()->search(self::HOST, 'acompte');
        $hit = array_values(array_filter($results['hits'], static fn (array $h): bool => 2 === $h['id']))[0] ?? null;

        self::assertNotNull($hit);
        self::assertArrayNotHasKey('content', $hit);
        self::assertIsArray($hit['_formatted']);
        self::assertIsString($hit['_formatted']['content']);
        self::assertStringContainsString('<em>acompte</em>', $hit['_formatted']['content']);
        self::assertStringNotContainsString(FrenchElision::BOUNDARY, json_encode($results));
    }

    public function testTheReturnedQueryIsTheOneTheVisitorTyped(): void
    {
        self::assertSame('l’acompte', $this->indexedSearcher()->search(self::HOST, 'l’acompte')['query']);
    }

    public function testBlankStopWordEntriesAndDuplicatesAreHarmless(): void
    {
        $searcher = $this->indexedSearcher(['search_stop_words' => ['', '  ', 'de', 'De', 'DE']]);

        self::assertSame([1, 2], $this->ids($searcher->search(self::HOST, 'acompte de')));
    }

    public function testTheAllStrategyRequiresEveryQueryWord(): void
    {
        self::assertSame([1, 2], $this->ids($this->indexedSearcher()->search(self::HOST, 'acompte réservation')));

        $searcher = $this->indexedSearcher(['search_matching_strategy' => 'all']);

        self::assertSame([2], $this->ids($searcher->search(self::HOST, 'acompte réservation')));
    }

    /**
     * @param array<string, mixed> $siteSettings
     */
    private function indexedSearcher(array $siteSettings = []): Searcher
    {
        $siteRegistry = $this->siteRegistry($siteSettings);
        $indexManager = $this->indexManager($siteRegistry);
        $indexManager->replaceAll(self::HOST, [
            $this->document(1, self::ACOMPTE_TITLE, 'Le solde est dû un mois avant le départ.'),
            $this->document(2, 'Préparer son sac', 'Un acompte de 30 % est demandé à la réservation.'),
            $this->document(3, 'Le matériel de bivouac', 'À emporter : tente et réchaud.'),
            $this->document(4, 'Partir d’Été', 'Les sentiers en juillet.'),
        ]);

        return new Searcher($indexManager, $siteRegistry, 20);
    }

    private function indexManager(SiteRegistry $siteRegistry): IndexManager
    {
        return new IndexManager($siteRegistry, $this->varDir, ['title', 'h1', 'tags', 'content'], ['host', 'locale', 'tags']);
    }

    /**
     * @param array<string, mixed> $siteSettings
     */
    private function siteRegistry(array $siteSettings = []): SiteRegistry
    {
        return new SiteRegistry(
            [self::HOST => [
                'hosts' => [self::HOST],
                'base_url' => 'https://'.self::HOST,
                'name' => 'Test',
                'locale' => 'fr',
                'locales' => 'fr',
                'template' => '@Pushword',
                'entity_can_override_filters' => false,
                ...$siteSettings,
            ]],
            new TemplateResolver(new Twig(new FilesystemLoader()), new ArrayAdapter()),
            new ParameterBag(['kernel.project_dir' => sys_get_temp_dir()]),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function document(int $id, string $title, string $content): array
    {
        return [
            'id' => $id,
            'host' => self::HOST,
            'locale' => 'fr',
            'slug' => 'page-'.$id,
            'url' => 'https://'.self::HOST.'/page-'.$id,
            'title' => $title,
            'h1' => $title,
            'tags' => [],
            'content' => $content,
        ];
    }

    /**
     * @param array{hits: list<array<string, mixed>>} $results
     *
     * @return list<mixed>
     */
    private function ids(array $results): array
    {
        $ids = array_column($results['hits'], 'id');
        sort($ids);

        return $ids;
    }
}
