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

    /**
     * The axis `pages_list` searches by default, and the one a blog whose
     * articles share neither a parent nor a slug prefix still has.
     */
    public function testATagSelectsAndItsNegationExcludes(): void
    {
        $this->page('tagged', tags: 'blog ia');
        $this->page('untagged');

        self::assertSame(['tagged'], $this->matching([['field' => 'tag', 'op' => 'has', 'value' => 'blog']]));
        self::assertSame(['untagged'], $this->matching([['field' => 'tag', 'op' => 'hasNot', 'value' => 'blog']]));
    }

    /** Matching on the quoted form is what keeps a tag from matching a longer one. */
    public function testALongerTagIsNotTheOneAsked(): void
    {
        $this->page('longer', tags: 'blogging');

        self::assertSame([], $this->matching([['field' => 'tag', 'op' => 'has', 'value' => 'blog']]));
    }

    public function testParentSelectsOnTheParentSlug(): void
    {
        $parent = $this->page('blog');
        $this->page('blog/child', parent: $parent);
        $this->page('orphan');

        self::assertSame(
            ['blog/child'],
            $this->matching([['field' => 'parent', 'op' => '=', 'value' => $this->prefix.'/blog']]),
        );
    }

    public function testAPageWithoutAParentIsNotThatChild(): void
    {
        $parent = $this->page('blog');
        $this->page('blog/child', parent: $parent);

        // The parent itself has none, so it lands on the `!=` side.
        self::assertSame(['blog', 'orphan'], $this->matching([
            ['field' => 'parent', 'op' => '!=', 'value' => $this->prefix.'/blog'],
        ], extra: fn (): Page => $this->page('orphan')));
    }

    /**
     * The shape `parent` cannot express without one trigger per rubric: the
     * articles sit at the root, share no slug prefix, and hang off a rubric that
     * itself hangs off the blog.
     */
    public function testAnAncestorSelectsTheWholeSectionHoweverDeep(): void
    {
        $blog = $this->page('blog');
        $rubric = $this->page('blog/ia', parent: $blog);
        $this->page('why-llms-hallucinate', parent: $rubric);
        $this->page('a-word-from-us', parent: $blog);
        $this->page('legal/terms');

        self::assertSame(
            ['a-word-from-us', 'blog/ia', 'why-llms-hallucinate'],
            $this->matching([['field' => 'ancestor', 'op' => '=', 'value' => $this->prefix.'/blog']]),
        );
    }

    public function testAnAncestorNegatedExcludesTheWholeSection(): void
    {
        $blog = $this->page('blog');
        $rubric = $this->page('blog/ia', parent: $blog);
        $this->page('why-llms-hallucinate', parent: $rubric);
        $this->page('legal/terms');

        // The blog page itself is not under itself.
        self::assertSame(['blog', 'legal/terms'], $this->matching([
            ['field' => 'ancestor', 'op' => '!=', 'value' => $this->prefix.'/blog'],
        ]));
    }

    /**
     * A slug is unique per host, not per site: resolving the section finds the
     * other host's blog as well, and the trigger's own host filter is what keeps
     * its articles out.
     */
    public function testAnAncestorStaysOnTheTriggersHosts(): void
    {
        $blog = $this->page('blog');
        $this->page('ours', parent: $blog);

        $otherBlog = $this->page('blog', host: 'pushword.piedweb.com');
        $this->page('theirs', parent: $otherBlog, host: 'pushword.piedweb.com');

        $under = [['field' => 'ancestor', 'op' => '=', 'value' => $this->prefix.'/blog']];

        self::assertSame(['ours'], $this->matching($under));
        // Both are under a page with that slug — watch both hosts and both come.
        self::assertSame(['ours', 'theirs'], $this->matching($under, hosts: ['localhost.dev', 'pushword.piedweb.com']));
    }

    /** A slug nobody published is a section with nothing in it, not every page. */
    public function testAnUnknownAncestorSelectsNothing(): void
    {
        $this->page('blog');

        self::assertSame([], $this->matching([['field' => 'ancestor', 'op' => '=', 'value' => 'no-such-page']]));
        self::assertSame(['blog'], $this->matching([['field' => 'ancestor', 'op' => '!=', 'value' => 'no-such-page']]));
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

    public function testAnAnyGroupTakesEitherCondition(): void
    {
        $this->page('tagged', tags: 'newsletter');
        $this->page('templated', template: 'article.html.twig');
        $this->page('neither');

        self::assertSame(['tagged', 'templated'], $this->matching(['any' => [
            ['field' => 'tag', 'op' => 'has', 'value' => 'newsletter'],
            ['field' => 'template', 'op' => '=', 'value' => 'article.html.twig'],
        ]]));
    }

    /**
     * What `any` must never reach: the trigger's hosts and its `triggerFrom` are
     * ANDed with the whole group, so no rule that can be written mails another
     * site or a back catalogue.
     */
    public function testAnAnyGroupNeverWidensPastTheGuards(): void
    {
        $this->page('ours', tags: 'newsletter');
        $this->page('otherHost', host: 'pushword.piedweb.com', tags: 'newsletter');
        $this->page('backCatalogue', tags: 'newsletter', publishedAt: '-2 days');

        self::assertSame(['ours'], $this->matching(['any' => [
            ['field' => 'tag', 'op' => 'has', 'value' => 'newsletter'],
            ['field' => 'tag', 'op' => 'has', 'value' => 'promo'],
        ]]));
    }

    /**
     * The rule that had no shape at all before a child could be a group: one
     * section, restricted to either of two tags.
     *
     * Two triggers do not replace it. An article carrying both tags would match
     * both, and each keeps its own log — `ContentTriggerLog` is unique on
     * (trigger, page) — so a contact in both segments would be mailed twice
     * about the same article.
     */
    public function testAGuardMayScopeAGroup(): void
    {
        $this->page('blog/featured', tags: 'featured');
        $this->page('blog/pinned', tags: 'pinned');
        $this->page('blog/plain');
        $this->page('legal/featured', tags: 'featured');

        self::assertSame(['blog/featured', 'blog/pinned'], $this->matching([
            ['field' => 'slug', 'op' => 'startsWith', 'value' => $this->prefix.'/blog/'],
            ['any' => [
                ['field' => 'tag', 'op' => 'has', 'value' => 'featured'],
                ['field' => 'tag', 'op' => 'has', 'value' => 'pinned'],
            ]],
        ]));
    }

    /** Depth is not capped at one: a group holds groups. */
    public function testGroupsNestFurther(): void
    {
        $this->page('a', template: 'article.html.twig', tags: 'featured');
        $this->page('b', template: 'other.html.twig', tags: 'featured');
        $this->page('c', template: 'article.html.twig', tags: 'pinned');

        self::assertSame(['a', 'c'], $this->matching([
            ['field' => 'slug', 'op' => 'startsWith', 'value' => $this->prefix.'/'],
            ['any' => [
                ['all' => [
                    ['field' => 'tag', 'op' => 'has', 'value' => 'featured'],
                    ['field' => 'template', 'op' => '=', 'value' => 'article.html.twig'],
                ]],
                ['field' => 'tag', 'op' => 'has', 'value' => 'pinned'],
            ]],
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
     * @param array<mixed>            $pageWhen a bare list of conditions, or one `{"any": [...]}` group
     * @param (callable(): Page)|null $extra    a page created after the others, to keep the expectation readable
     * @param list<string>            $hosts
     *
     * @return list<string> the matching slugs, prefix stripped, sorted
     */
    private function matching(array $pageWhen, ?callable $extra = null, array $hosts = ['localhost.dev']): array
    {
        if (null !== $extra) {
            $extra();
        }

        $pages = $this->matcher()->pages($this->trigger($pageWhen, $hosts), new DateTimeImmutable());

        $slugs = array_map(fn (Page $page): string => substr($page->getSlug(), \strlen($this->prefix) + 1), $pages);
        sort($slugs);

        return $slugs;
    }

    /**
     * @param array<mixed> $pageWhen
     * @param list<string> $hosts
     */
    private function trigger(array $pageWhen, array $hosts = ['localhost.dev']): ContentTrigger
    {
        // Every ANDed rule is scoped to this test's own pages: the fixtures
        // publish their own on the same host. A top-level `any` is deliberately
        // left unscoped — nesting it inside the guard is now possible and is
        // what testAGuardMayScopeAGroup covers, but the tests that assert what
        // `any` must *not* reach need it to be bounded by nothing but the
        // trigger's own guards.
        $scoped = \array_key_exists('any', $pageWhen) ? $pageWhen : [
            ['field' => 'slug', 'op' => 'startsWith', 'value' => $this->prefix.'/'],
            ...$pageWhen,
        ];

        return $this->createContentTrigger($this->audience, hosts: $hosts, pageWhen: $scoped);
    }

    /** @param array<string, mixed> $properties */
    private function page(string $slug, ?string $template = null, ?Page $parent = null, array $properties = [], string $host = 'localhost.dev', string $tags = '', string $publishedAt = '-10 minutes'): Page
    {
        $page = new Page();
        $page->host = $host;
        $page->setSlug($this->prefix.'/'.$slug);
        $page->setH1('Hello');
        $page->setTemplate($template);
        $page->setTags($tags);
        $page->setPublishedAt(new DateTime($publishedAt));

        if (null !== $parent) {
            $page->parentPage = $parent;
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
