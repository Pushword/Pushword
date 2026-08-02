<?php

namespace Pushword\Core\Tests\Twig;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\LinkCollectorService;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Twig\AppExtension;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class AppExtensionTest extends KernelTestCase
{
    private function makePage(string $slug, string $name, ?Page $parent = null): Page
    {
        $page = new Page();
        $page->host = 'localhost.dev';
        $page->slug = $slug;
        $page->name = $name;
        $page->parentPage = $parent;

        return $page;
    }

    private function extension(): AppExtension
    {
        self::bootKernel();
        self::getContainer()->get(SiteRegistry::class)->switchSite('localhost.dev');

        return self::getContainer()->get(AppExtension::class);
    }

    /** @return array<array-key, mixed> */
    private function generate(Page $page): array
    {
        $jsonLd = json_decode($this->extension()->generateBreadcrumbJsonLd($page), true);
        self::assertIsArray($jsonLd);
        self::assertIsArray($jsonLd['itemListElement']);

        return $jsonLd['itemListElement'];
    }

    public function testBreadcrumbPositionsAscendFromRootToCurrentPage(): void
    {
        $root = $this->makePage('root', 'Root');
        $child = $this->makePage('child', 'Child', $root);
        $leaf = $this->makePage('leaf', 'Leaf', $child);

        $items = $this->generate($leaf);

        self::assertCount(3, $items);
        self::assertSame(['Root', 'Child', 'Leaf'], array_column($items, 'name'));
        self::assertSame([1, 2, 3], array_column($items, 'position'));
    }

    public function testBreadcrumbNameStripsHtml(): void
    {
        $page = $this->makePage('page', '<strong>Bold</strong> Title');

        $items = $this->generate($page);

        self::assertSame(['Bold Title'], array_column($items, 'name'));
    }

    /** Twig delimiters in user content must not be re-parsed when the code block renders. */
    public function testCodeBlockNeutralizesTwigDelimiters(): void
    {
        $html = $this->extension()->codeBlock('{{ page.title }} {% if x %}', 'twig', 'sample');

        self::assertStringContainsString('<pre class="microlight" id="sample">', $html);
        self::assertStringContainsString('<code class="language-twig">', $html);
        self::assertStringContainsString('{<!---->{', $html);
        self::assertStringContainsString('{<!---->%', $html);
    }

    public function testCodeBlockDefaultsToJavascriptAndOmitsAnEmptyId(): void
    {
        $html = $this->extension()->codeBlock('const a = 1;');

        self::assertStringContainsString('<pre class="microlight">', $html);
        self::assertStringContainsString('<code class="language-js">', $html);
    }

    public function testEscapeTwigAlsoEscapesHtml(): void
    {
        self::assertSame('&lt;b&gt;a&lt;/b&gt;', $this->extension()->escapeTwig('<b>a</b>'));
    }

    public function testGetViewResolvesWithAndWithoutFallback(): void
    {
        $extension = $this->extension();

        self::assertStringContainsString('show_more.html.twig', $extension->getView('/component/show_more.html.twig', '@Pushword'));
        self::assertStringContainsString('show_more.html.twig', $extension->getView('/component/show_more.html.twig'));
    }

    public function testPregReplaceAcceptsBothScalarAndArraySubjects(): void
    {
        self::assertSame('b', AppExtension::pregReplace('a', '#a#', 'b'));
        self::assertSame(['b', 'b'], AppExtension::pregReplace(['a', 'a'], '#a#', 'b'));
    }

    public function testDateShortcodeResolvesAgainstTheCurrentSiteLocale(): void
    {
        self::assertSame('Sale '.date('Y'), $this->extension()->dateShortcode('Sale date(Y)'));
    }

    public function testContainsLinkToFindsAnHrefInTheGivenContent(): void
    {
        $extension = $this->extension();

        self::assertTrue($extension->containsLinkTo('contact', '<a href="/contact">Contact</a>'));
        self::assertFalse($extension->containsLinkTo('contact', '<p>No link here</p>'));
    }

    /** A page always "links" to itself — that is what stops a menu from self-linking. */
    public function testContainsLinkToIsTrueForTheCurrentPageItself(): void
    {
        $extension = $this->extension();
        self::getContainer()->get(SiteRegistry::class)->setCurrentPage($this->makePage('contact', 'Contact'));

        self::assertTrue($extension->containsLinkTo('contact', '<p>No link here</p>'));
    }

    /** With no content argument, the content being rendered is read from the stash. */
    public function testContainsLinkToFallsBackToTheStashedContent(): void
    {
        $extension = $this->extension();
        $apps = self::getContainer()->get(SiteRegistry::class);
        $apps->setCurrentPage($this->makePage('home', 'Home'));
        $apps->stash('content', "<a href='contact'>Contact</a>");

        self::assertTrue($extension->containsLinkTo('contact'));
    }

    public function testContainsLinkToFallsBackToTheCurrentPageContent(): void
    {
        $extension = $this->extension();
        $page = $this->makePage('home', 'Home');
        $page->mainContent = '<a href="/contact">Contact</a>';
        self::getContainer()->get(SiteRegistry::class)->setCurrentPage($page);

        self::assertTrue($extension->containsLinkTo('contact'));
    }

    public function testStringFilters(): void
    {
        $extension = $this->extension();

        self::assertSame(md5('a'), $extension->md5Filter('a'));
        self::assertSame('<b>', $extension->htmlEntityDecodeFilter('&lt;b&gt;'));
        self::assertSame('un-ete-a-paris', $extension->slugifyFilter('Un été à Paris'));
        self::assertSame('Vraiment&nbsp;? Oui', $extension->nicePunctuationFilter('Vraiment ? Oui'));
    }

    public function testFilesizeIsHumanReadable(): void
    {
        self::assertSame('1 K', $this->extension()->filesizeFunction(1024));
    }

    public function testClassExists(): void
    {
        $extension = $this->extension();

        self::assertTrue($extension->classExistsFunction(Page::class));
        self::assertFalse($extension->classExistsFunction('Pushword\Core\NopeNotHere'));
    }

    /** The dev-app leaves base_live_url unset, so it falls back to base_url. */
    public function testBaseReadsTheSiteConfiguration(): void
    {
        $extension = $this->extension();

        self::assertSame('https://localhost.dev', $extension->getBase());
        self::assertSame('https://localhost.dev', $extension->getBase(false));
    }

    /** Twig has no cast syntax; these exist so templates can compare like with like. */
    public function testScalarCasts(): void
    {
        $extension = $this->extension();

        self::assertSame(3, $extension->integer('3.7'));
        self::assertSame(3.5, $extension->float('3.5'));
        self::assertTrue($extension->boolean('1'));
        self::assertFalse($extension->boolean(''));
    }

    public function testSecurityHelpersReportAnAnonymousVisitor(): void
    {
        $extension = $this->extension();

        self::assertNull($extension->getUser());
        self::assertFalse($extension->isGranted('ROLE_ADMIN'));
    }

    /** Link collection lets a "related pages" block skip what the body already links. */
    public function testLinkCollectorHelpers(): void
    {
        $extension = $this->extension();
        $linkCollector = self::getContainer()->get(LinkCollectorService::class);
        $linkCollector->registerSlug('contact');

        self::assertSame(['contact'], $extension->getLinkedSlugs());
        self::assertTrue($extension->isSlugLinked('contact'));
        self::assertFalse($extension->isSlugLinked('about'));

        $pages = [$this->makePage('contact', 'Contact'), $this->makePage('about', 'About')];
        self::assertSame(['about'], array_map(static fn (Page $page): string => $page->slug, $extension->excludeLinked($pages)));
    }
}
