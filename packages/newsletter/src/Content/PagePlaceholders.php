<?php

namespace Pushword\Newsletter\Content;

use Pushword\Core\Component\EntityFilter\ValueObject\SplitContent;
use Pushword\Core\Entity\Page;
use Pushword\Core\Image\ImageCacheManager;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Twig\ContentExtension;
use Symfony\Component\String\TruncateMode;

use function Symfony\Component\String\u;

/**
 * What a page lends the steps triggered by its publication:
 *
 *     {{ page.h1 }}  {{ page.excerpt }}  {{ page.chapeau }}
 *     {{ page.mainContent }}  {{ page.url }}  {{ page.mainImage }}
 *
 * Only the values — putting them into a template is
 * {@see \Pushword\Newsletter\Trigger\PlaceholderRenderer}'s, and it does the
 * same for whatever any other source lends. Reading them is what needs a page:
 * an excerpt is an editorial decision, and a URL has to be built against the
 * page's own host rather than the one the tick happens to be running under.
 *
 * They are read once, when the occurrence is handled, and travel as plain
 * strings from there — which is why every later stage, Markdown, link
 * absolutization, `utm_*` tagging, needs to know nothing about pages.
 */
final readonly class PagePlaceholders
{
    /** Long enough for an opening, short enough that nobody scrolls it in a preview pane. */
    private const int EXCERPT_LENGTH = 300;

    /**
     * A teaser, not the article. Long enough to carry an argument far enough
     * that clicking through is a decision rather than a guess, short enough that
     * the mail is still an invitation to read the page.
     */
    private const int MAIN_CONTENT_LENGTH = 900;

    public function __construct(
        private SiteRegistry $siteRegistry,
        private ImageCacheManager $imageCacheManager,
        private ContentExtension $contentExtension,
    ) {
    }

    /**
     * Every value at once: the excerpt costs a content render, and a body that
     * quotes both it and the chapeau would otherwise pay for it twice.
     *
     * @return array<string, string>
     */
    public function map(Page $page): array
    {
        $split = $this->split($page);
        $chapeau = trim($split->getChapeau());

        return [
            'page.h1' => $page->h1,
            'page.excerpt' => $this->excerpt($split),
            'page.chapeau' => $chapeau,
            'page.mainContent' => $this->mainContent($split),
            'page.url' => $this->url($page),
            'page.mainImage' => $this->mainImageUrl($page),
        ];
    }

    /**
     * The page's canonical address, built from its own host rather than from the
     * audience's: one audience commonly spans several locale hosts, and a reader
     * must land on the page that was actually published.
     */
    public function url(Page $page): string
    {
        return $this->base($page).'/'.$page->getRealSlug();
    }

    /**
     * The article's own opening, never `searchExcerpt`: that property is written
     * for a search result page, and a meta description read in an inbox sounds
     * like one. The chapeau first, then the paragraphs before the first heading,
     * then the opening paragraph on its own — each conditional, hence the chain:
     * a chapeau needs a `<!--break-->`, an intro needs a page that asked for a
     * table of contents.
     *
     * The last one is truncated because it is an extract rather than an authored
     * accroche, and it comes back empty on a page that opens on a tool. Both are
     * deliberate: an empty excerpt leaves the mail at its title, its image and its
     * link, which says less but says nothing false.
     */
    private function excerpt(SplitContent $split): string
    {
        $chapeau = trim($split->getChapeau());

        if ('' !== $chapeau) {
            return $chapeau;
        }

        $intro = trim($split->getIntro());

        // Only when it opens on text: an intro is everything before the first
        // heading, which on a tool page is the widget.
        if (str_starts_with($intro, '<p')) {
            return $intro;
        }

        return u($split->getFirstParagraph())->truncate(self::EXCERPT_LENGTH, '…', TruncateMode::WordBefore)->toString();
    }

    /**
     * As much of the article as a mail should carry: its paragraphs from the very
     * top, run together and cut on a word boundary once they have said enough.
     *
     * Where {@see excerpt()} asks what the author wrote as an opening — and comes
     * back with a single line on a page whose first paragraph is a single line —
     * this asks how much of the piece the mail can hold, and keeps reading until
     * it has that much. It is the placeholder for a newsletter meant to be worth
     * opening on its own; the excerpt is the one for a mail that only points.
     *
     * It starts above the `<!--break-->`, so a page with a chapeau leads with it
     * here too and the two never contradict each other in the same mail.
     *
     * Paragraphs only, as plain text: the headings and widgets between them
     * belong to a page being read, not to a quotation, and a body separated by
     * blank lines is Markdown's own idea of prose. A page holding no paragraph
     * lends nothing, exactly as the excerpt does.
     */
    private function mainContent(SplitContent $split): string
    {
        $prose = implode("\n\n", $split->getParagraphs(withChapeau: true));

        return u($prose)->truncate(self::MAIN_CONTENT_LENGTH, '…', TruncateMode::WordBefore)->toString();
    }

    /**
     * Rendering happens on a tick, outside any request: nothing has bound the
     * registry to this page's host, so the body would be rendered against the
     * default site and every template lookup it makes — `view()` first among them
     * — would miss the host's own overrides. What a request's listener does at
     * its start, this does for the length of one render; restoring afterwards is
     * what makes it safe on a page in the middle of a loop over several hosts.
     */
    private function split(Page $page): SplitContent
    {
        $previousHost = $this->siteRegistry->get()->getMainHost();
        $this->siteRegistry->switchSite($page->host);

        try {
            return $this->contentExtension->mainContentSplit($page);
        } finally {
            $this->siteRegistry->switchSite($previousHost);
        }
    }

    /** Empty when the page has no main image — the author's template decides what that costs. */
    private function mainImageUrl(Page $page): string
    {
        $media = $page->getMainImage();

        if (null === $media) {
            return '';
        }

        // The original format rather than the site's preferred WebP: an inbox is
        // not a browser, and several major clients still decode neither.
        $extension = pathinfo($media->getFileName(), \PATHINFO_EXTENSION);

        return $this->base($page).$this->imageCacheManager->getBrowserPath($media, 'default', '' !== $extension ? $extension : null);
    }

    /**
     * The canonical base, never the live origin: these links are read months
     * later, from an inbox, and must point where the site is actually served —
     * which on a statically generated site is not where PHP runs.
     */
    private function base(Page $page): string
    {
        return rtrim($this->siteRegistry->get($page->host)->baseUrl, '/');
    }
}
