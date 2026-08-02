<?php

namespace Pushword\Newsletter\Tests\Content;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Newsletter\Content\PagePlaceholders;
use Pushword\Newsletter\Trigger\PlaceholderRenderer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class PagePlaceholdersTest extends KernelTestCase
{
    public function testTheFiveValuesAPageMayLend(): void
    {
        $rendered = $this->render(
            '# {{ page.h1 }}'."\n\n".'{{ page.chapeau }}'."\n\n".'{{ page.excerpt }}'."\n\n"
            .'![]({{ page.mainImage }})'."\n\n".'[Read]({{ page.url }})',
            $this->page(),
        );

        self::assertSame(
            '# Hello'."\n\n".'<p>The lede.</p>'."\n\n".'<p>The lede.</p>'."\n\n"
            .'![](https://localhost.dev/media/default/photo.jpg)'."\n\n"
            .'[Read](https://localhost.dev/blog/hello)',
            $rendered,
        );
    }

    /** Spacing inside the braces is Twig's habit; the substitution keeps it. */
    public function testWhitespaceInsideTheBracesIsTolerated(): void
    {
        self::assertSame('Hello / Hello', $this->render('{{page.h1}} / {{   page.h1   }}', $this->page()));
    }

    /** A typo must show up in the preview rather than vanish from the mail. */
    public function testAnUnknownPlaceholderIsLeftWhereItIs(): void
    {
        self::assertSame('{{ page.title }}', $this->render('{{ page.title }}', $this->page()));
    }

    /** The homepage has no slug of its own; the URL must still be one. */
    public function testTheHomepageStillGetsAUrl(): void
    {
        $page = $this->page();
        $page->slug = 'homepage';

        self::assertSame('https://localhost.dev/', $this->placeholders()->url($page));
    }

    public function testAPageWithoutAMainImageLendsAnEmptyString(): void
    {
        $page = $this->page();
        $page->setMainImage(null);

        self::assertSame('![]()', $this->render('![]({{ page.mainImage }})', $page));
    }

    /** A meta description read in an inbox sounds like a search result; the article does not. */
    public function testTheChapeauWinsOverTheAuthoredSearchExcerpt(): void
    {
        self::assertSame('<p>The lede.</p>', $this->render('{{ page.excerpt }}', $this->page()));
    }

    /** The paragraphs before the first heading, however many — they were written as one opening. */
    public function testWithoutAChapeauTheExcerptIsTheWholeIntro(): void
    {
        $page = $this->page();
        $page->setMainContent('First opening line.'."\n\n".'Second one.'."\n\n".'## A heading'."\n\n".'The rest.');

        self::assertSame(
            '<p>First opening line.</p> <p>Second one.</p>',
            (string) preg_replace('/\s+/', ' ', $this->render('{{ page.excerpt }}', $page)),
        );
    }

    /** No chapeau and no table of contents: the opening paragraph, on its own. */
    public function testWithoutAChapeauNorAnIntroTheExcerptIsTheFirstParagraph(): void
    {
        $page = $this->page();
        $page->setCustomProperty('toc', null);
        $page->setMainContent('The opening lines.'."\n\n".'## A heading'."\n\n".'The rest.');

        self::assertSame('The opening lines.', $this->render('{{ page.excerpt }}', $page));
    }

    public function testAnOverlongFirstParagraphIsTruncatedOnAWordBoundary(): void
    {
        $page = $this->page();
        $page->setCustomProperty('toc', null);
        $page->setMainContent(trim(str_repeat('Cheval ', 60)).'.'."\n\n".'## A heading');

        $rendered = $this->render('{{ page.excerpt }}', $page);

        self::assertStringEndsWith('Cheval…', $rendered);
        self::assertLessThanOrEqual(300, mb_strlen($rendered));
    }

    /**
     * A tool page: a widget, then the article. The widget's own labels are not an
     * opening, so the first real paragraph is what gets quoted.
     */
    public function testAnOpeningWidgetIsSkippedForTheParagraphBehindIt(): void
    {
        $page = $this->page();
        $page->setCustomProperty('toc', null);
        $page->setMainContent(
            '<div class="tool"><p>Budget</p><p>m/j</p></div>'."\n\n"
            .'The opening lines.'."\n\n".'## A heading',
        );

        self::assertSame('The opening lines.', $this->render('{{ page.excerpt }}', $page));
    }

    /** Nothing to quote is worth saying: the mail keeps its title, its image and its link. */
    public function testAPageThatOpensOnAToolAndHoldsNoParagraphLendsNothing(): void
    {
        $page = $this->page();
        $page->setCustomProperty('toc', null);
        $page->setMainContent('<div class="tool"><p>Budget</p></div>'."\n\n".'## A heading'."\n\n".'### Another');

        self::assertSame('', $this->render('{{ page.excerpt }}', $page));
    }

    /**
     * The body renders on a tick, outside any request, so nothing has bound the
     * registry to the page's host: left on the default site, `view()` and every
     * other lookup keyed on the current site miss this host's own overrides.
     */
    public function testTheBodyRendersUnderThePagesOwnHost(): void
    {
        $page = $this->page();
        $page->host = 'pushword.piedweb.com';
        $page->setCustomProperty('toc', null);
        $page->setMainContent('Rendered for {{ apps.getMainHost() }}.'."\n\n".'## A heading');

        self::assertSame('Rendered for pushword.piedweb.com.', $this->render('{{ page.excerpt }}', $page));
    }

    /**
     * One tick walks pages from several hosts, and a page on the default site
     * carries no host of its own: the page rendered before it must not lend it one.
     */
    public function testAPageWithoutAHostIsNotRenderedUnderTheOneBeforeIt(): void
    {
        $hosted = $this->page();
        $hosted->host = 'pushword.piedweb.com';

        $this->render('{{ page.excerpt }}', $hosted);

        $hostless = $this->page();
        $hostless->host = '';
        $hostless->setCustomProperty('toc', null);
        $hostless->setMainContent('Rendered for {{ apps.getMainHost() }}.'."\n\n".'## A heading');

        self::assertSame('Rendered for localhost.dev.', $this->render('{{ page.excerpt }}', $hostless));
    }

    public function testTheChapeauIsLentOnItsOwn(): void
    {
        self::assertSame('<p>The lede.</p>', $this->render('{{ page.chapeau }}', $this->page()));
    }

    /** A body is HTML-in-Markdown: what the page lends belongs there as it stands. */
    public function testTheBodyKeepsTheMarkupThePageCarries(): void
    {
        $page = $this->page();
        $page->setH1('The <em>real</em> guide');

        self::assertSame('# The <em>real</em> guide', $this->render('# {{ page.h1 }}', $page));
    }

    public function testASubjectGetsPlainTextOnly(): void
    {
        $page = $this->page();
        $page->setH1('The <em>real</em> guide<br>second line');

        self::assertSame(
            'New: The real guide second line — The lede.',
            $this->renderSubject('New: {{ page.h1 }} — {{ page.excerpt }}', $page),
        );
    }

    /** Stripping runs before decoding, so an escaped tag stays escaped text. */
    public function testASubjectDecodesEntitiesWithoutRevivingTags(): void
    {
        $page = $this->page();
        $page->setH1('Rock &amp; Roll &lt;em&gt;');

        self::assertSame('Rock & Roll <em>', $this->renderSubject('{{ page.h1 }}', $page));
    }

    private function page(): Page
    {
        $page = new Page();
        $page->host = 'localhost.dev';
        $page->slug = 'blog/hello';
        $page->setH1('Hello');
        $page->setSearchExcerpt('What it is about.');
        $page->setMainContent('The lede.'."\n\n".'<!--break-->'."\n\n".'The opening lines.'."\n\n".'## A heading'."\n\n".'The rest.');

        // The intro is only isolated on a page that asked for a table of contents.
        $page->setCustomProperty('toc', true);

        // Not persisted: the renderer only ever reads the file name, but a page
        // refuses a main image that has no dimensions.
        $media = new Media();
        $media->setFileName('photo.jpg');
        $media->setDimensions([800, 600]);

        $page->setMainImage($media);

        return $page;
    }

    /**
     * What a page lends and how it reaches a template are two objects now; every
     * case here is about the pair, so the tests compose them as the runner does.
     */
    private function render(string $template, Page $page): string
    {
        return $this->renderer()->render($template, $this->placeholders()->map($page));
    }

    private function renderSubject(string $template, Page $page): string
    {
        return $this->renderer()->renderSubject($template, $this->placeholders()->map($page));
    }

    private function placeholders(): PagePlaceholders
    {
        self::bootKernel();

        return self::getContainer()->get(PagePlaceholders::class);
    }

    private function renderer(): PlaceholderRenderer
    {
        self::bootKernel();

        return self::getContainer()->get(PlaceholderRenderer::class);
    }
}
