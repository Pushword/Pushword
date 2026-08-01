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
 * Substitutes the handful of page values a content trigger's subject and body
 * may quote:
 *
 *     {{ page.h1 }}  {{ page.excerpt }}  {{ page.chapeau }}
 *     {{ page.url }}  {{ page.mainImage }}
 *
 * The braces are borrowed from Twig; the evaluation is not. This is `strtr` with
 * a regex, exactly like the campaign body's `%name%` — a mail body is authored
 * content, and rendering it as a template would mean evaluating editor input at
 * send time. Anything else between braces is left where it is, so a typo shows
 * up in the preview instead of vanishing.
 *
 * Substitution happens once, when the campaign is created. What the campaign
 * stores is plain Markdown, which is why every later stage — Markdown, link
 * absolutization, `utm_*` tagging — needs to know nothing about pages.
 */
final readonly class PagePlaceholders
{
    private const string PATTERN = '/\{\{\s*page\.(h1|excerpt|chapeau|url|mainImage)\s*\}\}/';

    /** Long enough for an opening, short enough that nobody scrolls it in a preview pane. */
    private const int EXCERPT_LENGTH = 300;

    public function __construct(
        private SiteRegistry $siteRegistry,
        private ImageCacheManager $imageCacheManager,
        private ContentExtension $contentExtension,
    ) {
    }

    /** The body is authored HTML-in-Markdown: what the page lends goes in as it stands. */
    public function render(string $template, Page $page): string
    {
        return $this->substitute($template, $page, false);
    }

    /**
     * A subject line is a header, not a document. An h1 routinely carries `<em>`,
     * `<br>` or a `<span class="…">`, and the excerpt's fallback is rendered HTML
     * by construction — both would reach the inbox as literal markup.
     */
    public function renderSubject(string $template, Page $page): string
    {
        return $this->substitute($template, $page, true);
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

    private function substitute(string $template, Page $page, bool $plainText): string
    {
        if (! str_contains($template, '{{')) {
            return $template;
        }

        return preg_replace_callback(
            self::PATTERN,
            function (array $match) use ($page, $plainText): string {
                $value = $this->value($match[1], $page);

                return $plainText ? $this->plainText($value) : $value;
            },
            $template,
        ) ?? $template;
    }

    private function value(string $key, Page $page): string
    {
        return match ($key) {
            'h1' => $page->getH1(),
            'excerpt' => $this->excerpt($page),
            'chapeau' => trim($this->split($page)->getChapeau()),
            'url' => $this->url($page),
            default => $this->mainImageUrl($page),
        };
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
    private function excerpt(Page $page): string
    {
        $split = $this->split($page);

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

    private function split(Page $page): SplitContent
    {
        return $this->contentExtension->mainContentSplit($page);
    }

    /**
     * Tags become a space rather than nothing: a `<br>` between two words must not
     * glue them. Entities are decoded afterwards — never before, or an escaped
     * `&lt;em&gt;` would turn into a tag on its way out.
     */
    private function plainText(string $html): string
    {
        $text = html_entity_decode(strip_tags(str_replace('<', ' <', $html)), \ENT_QUOTES | \ENT_HTML5);

        return trim((string) preg_replace('/\s+/', ' ', $text));
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
        return rtrim($this->siteRegistry->get($page->host)->getBaseUrl(), '/');
    }
}
