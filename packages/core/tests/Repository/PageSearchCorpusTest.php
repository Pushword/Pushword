<?php

namespace Pushword\Core\Tests\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Exception;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Query\Search\PageSearchVocabulary;
use Pushword\Core\Query\Search\SearchParser;
use Pushword\Core\Repository\FilterWhereParser;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Utils\StringToDQLCriteria;
use Stringable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * What every `pages_list` search compiles to, frozen.
 *
 * This is an oracle, not a specification: it records the DQL the current engine
 * produces for a corpus covering the whole documented vocabulary, the operator
 * combinations, and the raw array form. A refactor of the parser or of the
 * compiler is expected to leave every line below untouched; a line that does
 * change has to be read and justified, which is the entire point.
 *
 * Entries marked CHANGED record a line that did move, and why. Everything else
 * has compiled to the same DQL since before there was a parser.
 */
#[Group('integration')]
final class PageSearchCorpusTest extends KernelTestCase
{
    private const string HOST = 'localhost.dev';

    private const string PARENT_SLUG = 'search-corpus-parent';

    private const string CURRENT_SLUG = 'search-corpus-current';

    private const string CHILD_SLUG = 'search-corpus-child';

    private const array ALL_SLUGS = [self::CHILD_SLUG, self::CURRENT_SLUG, self::PARENT_SLUG];

    private EntityManagerInterface $entityManager;

    private Page $currentPage;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        // parent → current → child: enough tree for every contextual search to
        // compile to something other than a fallback.
        $parent = $this->createPage(self::PARENT_SLUG, null);
        $current = $this->createPage(self::CURRENT_SLUG, $parent);
        $this->createPage(self::CHILD_SLUG, $current);

        // assigning parentPage does not maintain the inverse side, so `grandchildren`
        // would read an empty collection off the entity that just created it.
        // Re-reading makes the fixture behave like a page loaded from a request.
        $this->entityManager->clear();
        $this->currentPage = $this->page(self::CURRENT_SLUG);
    }

    private function page(string $slug): Page
    {
        $page = $this->entityManager->getRepository(Page::class)->findOneBy(['slug' => $slug]);
        self::assertInstanceOf(Page::class, $page);

        return $page;
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

    private function createPage(string $slug, ?Page $parent): Page
    {
        $page = new Page();
        $page->host = self::HOST;
        $page->locale = 'en';
        $page->slug = $slug;
        $page->h1 = 'Corpus fixture '.$slug;
        $page->mainContent = 'Corpus fixture content.';
        $page->parentPage = $parent;

        $this->entityManager->persist($page);
        $this->entityManager->flush();

        return $page;
    }

    /**
     * The whole documented vocabulary, one entry per row of `pages-list.md`,
     * plus the shapes the audited downstream sites actually write.
     *
     * @return iterable<string, array{string, string, array<string, mixed>}>
     */
    public static function stringCorpus(): iterable
    {
        yield 'an empty search looks for an empty tag' => [
            '', "p.tags LIKE :w0 ESCAPE '!'", ['w0' => '%""%'],
        ];

        yield 'a bare word is a tag' => [
            'blog', "p.tags LIKE :w0 ESCAPE '!'", ['w0' => '%"blog"%'],
        ];

        // A namespaced tag is a production convention (GA carries `type:product`
        // on 1167 pages) and reaches the same fallback as an unknown prefix.
        yield 'a namespaced tag goes through the same fallback' => [
            'type:product', "p.tags LIKE :w0 ESCAPE '!'", ['w0' => '%"type:product"%'],
        ];

        yield 'an unknown prefix is a tag, not an error' => [
            'tags:blog', "p.tags LIKE :w0 ESCAPE '!'", ['w0' => '%"tags:blog"%'],
        ];

        yield 'children' => [
            'children', 'p.parentPage = :w0', ['w0' => '{current}'],
        ];

        // Case insensitivity is load-bearing: piedvert writes `CHILDREN`.
        yield 'children is case insensitive' => [
            'CHILDREN', 'p.parentPage = :w0', ['w0' => '{current}'],
        ];

        yield 'sisters' => [
            'sisters', 'p.parentPage = :w0', ['w0' => '{parent}'],
        ];

        yield 'parent_children is the deprecated alias of sisters' => [
            'parent_children', 'p.parentPage = :w0', ['w0' => '{parent}'],
        ];

        yield 'grandchildren' => [
            'grandchildren', 'p.parentPage IN (:w0)', ['w0' => '{children}'],
        ];

        yield 'children_children is the deprecated alias of grandchildren' => [
            'children_children', 'p.parentPage IN (:w0)', ['w0' => '{children}'],
        ];

        yield 'related is sisters bounded by the current id' => [
            'related', 'p.parentPage = :w0 AND p.id < :w1', ['w0' => '{parent}', 'w1' => '{current+3}'],
        ];

        yield 'related:comment: swaps the sisters condition for a comment match' => [
            'related:comment:blog',
            'p.mainContent LIKE :w0 AND p.id < :w1',
            ['w0' => '%<!--blog-->%', 'w1' => '{current+3}'],
        ];

        yield 'comment:' => [
            'comment:blog', 'p.mainContent LIKE :w0', ['w0' => '%<!--blog-->%'],
        ];

        yield 'title: searches the two titles' => [
            'title:foo', 'p.h1 LIKE :w0 OR p.title LIKE :w1', ['w0' => '%foo%', 'w1' => '%foo%'],
        ];

        yield 'content: adds the body to title:' => [
            'content:foo',
            'p.h1 LIKE :w0 OR p.title LIKE :w1 OR p.mainContent LIKE :w2',
            ['w0' => '%foo%', 'w1' => '%foo%', 'w2' => '%foo%'],
        ];

        yield 'slug:' => [
            'slug:my-page', 'p.slug LIKE :w0', ['w0' => 'my-page'],
        ];

        yield 'page: is the alias of slug:' => [
            'page:my-page', 'p.slug LIKE :w0', ['w0' => 'my-page'],
        ];

        // Documented: the value goes into LIKE unescaped, so % is a wildcard on purpose.
        yield 'slug: passes its wildcards through' => [
            'slug:%partial%', 'p.slug LIKE :w0', ['w0' => '%partial%'],
        ];

        // CHANGED deliberately. It used to match a substring of the serialised
        // JSON (`%"productCode":"ALBGP0001"%`), which depended on the encoder's
        // spacing and could never match a non-string value: `prop.count = 3` was
        // unwritable. JSON_SCALAR reads the member.
        yield 'customProperty: reads the JSON member' => [
            'customProperty:productCode:ALBGP0001',
            "JSON_SCALAR(p.customProperties, '\$.productCode') = :w0",
            ['w0' => 'ALBGP0001'],
        ];

        yield 'prop: is the shorter spelling of customProperty:' => [
            'prop:productCode:ALBGP0001',
            "JSON_SCALAR(p.customProperties, '\$.productCode') = :w0",
            ['w0' => 'ALBGP0001'],
        ];

        // Documented, and preserved: without a value there is no property to
        // read, so the whole term stays a tag.
        yield 'customProperty: without a value stays a tag' => [
            'customProperty:keyonly', "p.tags LIKE :w0 ESCAPE '!'", ['w0' => '%"customProperty:keyonly"%'],
        ];

        yield 'locale: selects on the column' => [
            'locale:fr', 'p.locale = :w0', ['w0' => 'fr'],
        ];

        // The section is resolved before the query is built, so the condition is
        // an IN over ids rather than a join.
        yield 'ancestor: selects the whole section by its parents' => [
            'ancestor:'.self::PARENT_SLUG, 'p.parentPage IN (:w0)', ['w0' => '{section}'],
        ];

        // Nothing sits under a page that does not exist. A constant, not a
        // dropped condition: inside an OR group, losing it would widen the list
        // to everything instead of narrowing it to nothing.
        yield 'ancestor: on an unknown slug is a false constant' => [
            'ancestor:no-such-page', '1 = 0', [],
        ];

        yield 'OR of two slugs' => [
            'slug:a OR slug:b', 'p.slug LIKE :w0 OR p.slug LIKE :w1', ['w0' => 'a', 'w1' => 'b'],
        ];

        yield 'OR of a slug and a tag' => [
            'slug:a OR tmb', "p.slug LIKE :w0 OR p.tags LIKE :w1 ESCAPE '!'", ['w0' => 'a', 'w1' => '%"tmb"%'],
        ];

        // The two-level tree the flat newsletter IR cannot represent.
        yield 'AND of a subtree and a leaf' => [
            'title:foo AND children',
            '(p.h1 LIKE :w0 OR p.title LIKE :w1) AND p.parentPage = :w2',
            ['w0' => '%foo%', 'w1' => '%foo%', 'w2' => '{current}'],
        ];

        yield 'the documented OR chain' => [
            'parent_children OR related OR page:custom-slug',
            'p.parentPage = :w0 OR (p.parentPage = :w1 AND p.id < :w2) OR p.slug LIKE :w3',
            ['w0' => '{parent}', 'w1' => '{parent}', 'w2' => '{current+3}', 'w3' => 'custom-slug'],
        ];

        yield 'the documented AND chain' => [
            'parent_children AND related AND page:custom-slug',
            'p.parentPage = :w0 AND (p.parentPage = :w1 AND p.id < :w2) AND p.slug LIKE :w3',
            ['w0' => '{parent}', 'w1' => '{parent}', 'w2' => '{current+3}', 'w3' => 'custom-slug'],
        ];

        yield 'the documented related OR' => [
            'related:comment:blog OR related',
            '(p.mainContent LIKE :w0 AND p.id < :w1) OR (p.parentPage = :w2 AND p.id < :w3)',
            ['w0' => '%<!--blog-->%', 'w1' => '{current+3}', 'w2' => '{parent}', 'w3' => '{current+3}'],
        ];

        // CHANGED deliberately: parentheses used to be ordinary characters and
        // ended up inside the tag name — `parent_children AND related OR …`
        // compiled to `p.tags LIKE '%"parent_children AND related"%' OR …`,
        // because the split happened on ` OR ` first and the AND half was never
        // split again. Ungrouped, that search is now refused
        // ({@see \Pushword\Core\Tests\Query\SearchParserTest}); grouped, it is
        // this.
        yield 'parentheses group explicitly' => [
            '(parent_children AND related) OR page:custom-slug',
            '(p.parentPage = :w0 AND (p.parentPage = :w1 AND p.id < :w2)) OR p.slug LIKE :w3',
            ['w0' => '{parent}', 'w1' => '{parent}', 'w2' => '{current+3}', 'w3' => 'custom-slug'],
        ];

        // The other grouping, which had no syntax at all before.
        yield 'parentheses can also group the OR' => [
            'parent_children AND (related OR page:custom-slug)',
            'p.parentPage = :w0 AND ((p.parentPage = :w1 AND p.id < :w2) OR p.slug LIKE :w3)',
            ['w0' => '{parent}', 'w1' => '{parent}', 'w2' => '{current+3}', 'w3' => 'custom-slug'],
        ];

        yield 'template: selects on the column' => [
            'template:article.html.twig', 'p.template = :w0', ['w0' => 'article.html.twig'],
        ];

        yield 'parent: reaches the parent join by slug' => [
            'parent:blog', 'parent.slug = :w0', ['w0' => 'blog'],
        ];

        yield 'tag: is the explicit form of a bare word' => [
            'tag:blog', "p.tags LIKE :w0 ESCAPE '!'", ['w0' => '%"blog"%'],
        ];

        // A tag whose name merely contains a parenthesis is not a group: `(` is
        // structural only where a term may start.
        yield 'a parenthesis inside a term stays a character' => [
            'foo (bar)', "p.tags LIKE :w0 ESCAPE '!'", ['w0' => '%"foo (bar)"%'],
        ];

        // Same reason `ORANGE` is not an `OR`: a conjunction is a whole word.
        yield 'a word merely starting with OR is a tag' => [
            'ORANGE', "p.tags LIKE :w0 ESCAPE '!'", ['w0' => '%"ORANGE"%'],
        ];

        yield 'a lowercase or is a tag, not a conjunction' => [
            'a or b', "p.tags LIKE :w0 ESCAPE '!'", ['w0' => '%"a or b"%'],
        ];
    }

    /**
     * The raw array form: the escape hatch, deliberately unvalidated, and the
     * one downstream sites reach for (piedvert's sitemap writes the first entry).
     *
     * @return iterable<string, array{array<mixed>, string, array<string, mixed>}>
     */
    public static function arrayCorpus(): iterable
    {
        yield 'IS NULL needs no parameter' => [
            [['parentPage', 'IS', null]], 'p.parentPage IS NULL', [],
        ];

        yield 'a single condition' => [
            [['slug', 'LIKE', 'blog']], 'p.slug LIKE :w0', ['w0' => 'blog'],
        ];

        yield 'a flat OR' => [
            [['title', 'LIKE', '%x%'], 'OR', ['title', 'LIKE', '%y%']],
            'p.title LIKE :w0 OR p.title LIKE :w1',
            ['w0' => '%x%', 'w1' => '%y%'],
        ];

        // The capability the string surface has no syntax for: arbitrary depth.
        yield 'a nested tree parenthesises the inner group' => [
            [[['h1', 'LIKE', '%a%'], 'OR', ['h1', 'LIKE', '%b%']], ['slug', 'LIKE', 'c']],
            '(p.h1 LIKE :w0 OR p.h1 LIKE :w1) AND p.slug LIKE :w2',
            ['w0' => '%a%', 'w1' => '%b%', 'w2' => 'c'],
        ];

        // Reaches the parent join buildPageQuery() already declares.
        yield 'key_prefix reaches a joined alias' => [
            [['key' => 'slug', 'operator' => '=', 'value' => 'blog', 'key_prefix' => 'parent.']],
            'parent.slug = :w0',
            ['w0' => 'blog'],
        ];

        yield 'IN wraps its parameter' => [
            [['parentPage', 'IN', [1, 2, 3]]], 'p.parentPage IN (:w0)', ['w0' => [1, 2, 3]],
        ];
    }

    /**
     * @param array<string, mixed> $parameters
     */
    #[DataProvider('stringCorpus')]
    public function testAStringSearchCompilesTo(string $search, string $dql, array $parameters): void
    {
        $this->assertCompilesTo(
            new StringToDQLCriteria($search, $this->currentPage)->retrieve(),
            $dql,
            $parameters,
        );
    }

    /**
     * @param array<mixed>         $where
     * @param array<string, mixed> $parameters
     */
    #[DataProvider('arrayCorpus')]
    public function testAnArraySearchCompilesTo(array $where, string $dql, array $parameters): void
    {
        $this->assertCompilesTo($where, $dql, $parameters);
    }

    /**
     * An empty group is the one shape the compiler cannot take. It throws a
     * message-less exception rather than producing `()`, which would be a syntax
     * error further down; the parser must never emit one.
     */
    public function testAnEmptyGroupIsRefused(): void
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()->select('p')->from(Page::class, 'p');

        $this->expectException(Exception::class);

        new FilterWhereParser($queryBuilder, [[], ['slug', 'LIKE', 'a']])->parseAndAdd();
    }

    /**
     * A NULL comparison has no parameter to bind, so an operator that would need
     * one is a mistake worth naming rather than a query silently comparing
     * against nothing.
     */
    public function testANullValueOnlyWorksWithIs(): void
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()->select('p')->from(Page::class, 'p');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/"=" is forbidden for a null value/');

        new FilterWhereParser($queryBuilder, [['parentPage', '=', null]])->parseAndAdd();
    }

    /**
     * `parent:` reads through a join, and the query it lands in may already have
     * declared one — a page list joins the parent to render it. Declaring it
     * twice is a DQL error, so the strategy has to look before it joins.
     */
    public function testTheParentJoinIsNotDeclaredTwice(): void
    {
        // buildPageQuery() already joins parentPage as `parent`.
        $queryBuilder = self::getContainer()->get(PageRepository::class)->getPublishedPageQueryBuilder(
            self::HOST,
            new SearchParser(new PageSearchVocabulary($this->currentPage))->parse('parent:'.self::PARENT_SLUG),
        );

        /** @var array<string, array<Join>> $joins */
        $joins = $queryBuilder->getDQLPart('join');

        $parentJoins = [];
        foreach ($joins as $rootJoins) {
            foreach ($rootJoins as $join) {
                if ('parent' === $join->getAlias()) {
                    $parentJoins[] = $join->getJoin();
                }
            }
        }

        self::assertCount(1, $parentJoins, 'The parent join was declared twice.');

        // And the query still runs: a duplicate alias would only surface here.
        self::assertIsArray($queryBuilder->getQuery()->getResult());
    }

    /**
     * @param array<mixed>         $where
     * @param array<string, mixed> $parameters
     */
    private function assertCompilesTo(array $where, string $dql, array $parameters): void
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()->select('p')->from(Page::class, 'p');

        new FilterWhereParser($queryBuilder, $where)->parseAndAdd();

        $where = $queryBuilder->getDQLPart('where');
        self::assertInstanceOf(Stringable::class, $where);
        self::assertSame($dql, (string) $where);

        $actual = [];
        foreach ($queryBuilder->getParameters() as $parameter) {
            $actual[$parameter->getName()] = $parameter->getValue();
        }

        self::assertSame(array_map($this->resolve(...), $parameters), $actual);
    }

    /** Fixture ids are not knowable from a static data provider; these stand in for them. */
    private function resolve(mixed $expected): mixed
    {
        $current = $this->currentPage->id ?? 0;

        return match ($expected) {
            '{current}' => $current,
            '{current+3}' => $current + 3,
            '{parent}' => $this->currentPage->parentPage->id ?? 0,
            '{children}' => [$this->childId()],
            // The named page plus everything below it — the parents a page of
            // that section can have.
            '{section}' => [$this->page(self::PARENT_SLUG)->id ?? 0, $current, $this->childId()],
            default => $expected,
        };
    }

    private function childId(): int
    {
        return $this->page(self::CHILD_SLUG)->id ?? 0;
    }
}
