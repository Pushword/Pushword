<?php

namespace Pushword\Newsletter\Tests\Content;

use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Newsletter\Content\PageMatcher;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\ContentTrigger;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;

/**
 * The page grammar compiled into a query. {@see PageCriteriaTest} checks what
 * the language accepts; this checks what it then selects, which is where a
 * mistyped join or a stray LIKE wildcard would otherwise ship unnoticed.
 */
#[Group('integration')]
final class PageMatcherTest extends AbstractNewsletterTestCase
{
    private string $prefix = '';

    private Audience $audience;

    /** @var list<int> */
    private array $pageIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->prefix = 'pm'.bin2hex(random_bytes(4));
        $this->audience = $this->createAudience();
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->pageIds) as $pageId) {
            $this->entityManager->getConnection()->executeStatement('DELETE FROM page WHERE id = :id', ['id' => $pageId]);
        }

        $this->pageIds = [];
        parent::tearDown();
    }

    public function testSlugPrefixSelectsAndItsNegationExcludes(): void
    {
        $this->page('blog/hello');
        $this->page('legal/terms');

        self::assertSame(['blog/hello'], $this->matching([['field' => 'slug', 'op' => 'startsWith', 'value' => $this->prefix.'/blog/']]));
        self::assertSame(['legal/terms'], $this->matching([['field' => 'slug', 'op' => 'notStartsWith', 'value' => $this->prefix.'/blog/']]));
    }

    /**
     * `_` is a LIKE wildcard and a legal slug character (Page::normalizeSlug
     * keeps it), so an unescaped prefix would quietly widen the rule.
     */
    public function testAnUnderscoreInThePrefixIsAPlainCharacter(): void
    {
        $this->page('a_b/one');
        $this->page('axb/two');

        self::assertSame(['a_b/one'], $this->matching([['field' => 'slug', 'op' => 'startsWith', 'value' => $this->prefix.'/a_b/']]));
    }

    public function testTemplateSelects(): void
    {
        $this->page('with', template: 'article.html.twig');
        $this->page('other', template: 'page.html.twig');

        self::assertSame(['with'], $this->matching([['field' => 'template', 'op' => '=', 'value' => 'article.html.twig']]));
    }

    /**
     * An absent template is the site's default one — a known value, and
     * genuinely not the one being excluded. Unlike a missing property, NULL
     * therefore belongs on the `!=` side.
     */
    public function testAPageWithoutATemplateIsNotThatTemplate(): void
    {
        $this->page('with', template: 'article.html.twig');
        $this->page('none');

        self::assertSame(['none'], $this->matching([['field' => 'template', 'op' => '!=', 'value' => 'article.html.twig']]));
    }

    public function testParentPageSelectsOnTheParentSlug(): void
    {
        $parent = $this->page('blog');
        $this->page('blog/child', parent: $parent);
        $this->page('orphan');

        self::assertSame(
            ['blog/child'],
            $this->matching([['field' => 'parentPage', 'op' => '=', 'value' => $this->prefix.'/blog']]),
        );
    }

    public function testAPageWithoutAParentIsNotThatChild(): void
    {
        $parent = $this->page('blog');
        $this->page('blog/child', parent: $parent);

        // The parent itself has none, so it lands on the `!=` side.
        self::assertSame(['blog', 'orphan'], $this->matching([
            ['field' => 'parentPage', 'op' => '!=', 'value' => $this->prefix.'/blog'],
        ], extra: fn (): Page => $this->page('orphan')));
    }

    public function testAPropertySelectsOnItsValue(): void
    {
        $this->page('yes', properties: ['audience' => 'readers']);
        $this->page('no', properties: ['audience' => 'staff']);

        self::assertSame(['yes'], $this->matching([['field' => 'prop.audience', 'op' => '=', 'value' => 'readers']]));
    }

    public function testAPropertyPresenceSelects(): void
    {
        $this->page('flagged', properties: ['noNewsletter' => true]);
        $this->page('plain');

        self::assertSame(['flagged'], $this->matching([['field' => 'prop.noNewsletter', 'op' => 'isSet']]));
        self::assertSame(['plain'], $this->matching([['field' => 'prop.noNewsletter', 'op' => 'isNotSet']]));
    }

    /** A missing property is unknown, not "different from x" — the segment rule, applied to pages. */
    public function testAMissingPropertyIsNotDifferentFromAValue(): void
    {
        $this->page('staff', properties: ['audience' => 'staff']);
        $this->page('unknown');

        self::assertSame(['staff'], $this->matching([['field' => 'prop.audience', 'op' => '!=', 'value' => 'readers']]));
    }

    public function testConditionsAreAnded(): void
    {
        $this->page('blog/one', template: 'article.html.twig');
        $this->page('blog/two');
        $this->page('legal/three', template: 'article.html.twig');

        self::assertSame(['blog/one'], $this->matching([
            ['field' => 'slug', 'op' => 'startsWith', 'value' => $this->prefix.'/blog/'],
            ['field' => 'template', 'op' => '=', 'value' => 'article.html.twig'],
        ]));
    }

    public function testTheLimitBoundsWhatIsReturnedButNotWhatIsCounted(): void
    {
        $this->page('one');
        $this->page('two');
        $this->page('three');

        $trigger = $this->trigger([]);
        $now = new DateTimeImmutable();

        self::assertCount(2, $this->matcher()->pages($trigger, $now, 2));
        self::assertSame(3, $this->matcher()->count($trigger, $now));
    }

    /**
     * @param array<int, array<string, mixed>> $pageWhen
     * @param (callable(): Page)|null          $extra    a page created after the others, to keep the expectation readable
     *
     * @return list<string> the matching slugs, prefix stripped, sorted
     */
    private function matching(array $pageWhen, ?callable $extra = null): array
    {
        if (null !== $extra) {
            $extra();
        }

        $pages = $this->matcher()->pages($this->trigger($pageWhen), new DateTimeImmutable());

        $slugs = array_map(fn (Page $page): string => substr($page->getSlug(), \strlen($this->prefix) + 1), $pages);
        sort($slugs);

        return $slugs;
    }

    /** @param array<int, array<string, mixed>> $pageWhen */
    private function trigger(array $pageWhen): ContentTrigger
    {
        // Every rule is scoped to this test's own pages: the fixtures publish
        // their own on the same host.
        return $this->createContentTrigger($this->audience, pageWhen: [
            ['field' => 'slug', 'op' => 'startsWith', 'value' => $this->prefix.'/'],
            ...$pageWhen,
        ]);
    }

    /** @param array<string, mixed> $properties */
    private function page(string $slug, ?string $template = null, ?Page $parent = null, array $properties = []): Page
    {
        $page = new Page();
        $page->host = 'localhost.dev';
        $page->setSlug($this->prefix.'/'.$slug);
        $page->setH1('Hello');
        $page->setTemplate($template);
        $page->setPublishedAt(new DateTime('-10 minutes'));

        if (null !== $parent) {
            $page->setParentPage($parent);
        }

        foreach ($properties as $key => $value) {
            $page->setCustomProperty($key, $value);
        }

        $this->entityManager->persist($page);
        $this->entityManager->flush();

        $pageId = $page->id;
        self::assertIsInt($pageId);
        $this->pageIds[] = $pageId;

        return $page;
    }

    private function matcher(): PageMatcher
    {
        return self::getContainer()->get(PageMatcher::class);
    }
}
