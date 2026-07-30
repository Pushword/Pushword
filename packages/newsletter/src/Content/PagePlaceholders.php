<?php

namespace Pushword\Newsletter\Content;

use Pushword\Core\Entity\Page;
use Pushword\Core\Image\ImageCacheManager;
use Pushword\Core\Site\SiteRegistry;

/**
 * Substitutes the handful of page values a content trigger's subject and body
 * may quote:
 *
 *     {{ page.h1 }}  {{ page.excerpt }}  {{ page.url }}  {{ page.mainImage }}
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
    private const string PATTERN = '/\{\{\s*page\.(h1|excerpt|url|mainImage)\s*\}\}/';

    public function __construct(
        private SiteRegistry $siteRegistry,
        private ImageCacheManager $imageCacheManager,
    ) {
    }

    public function render(string $template, Page $page): string
    {
        if (! str_contains($template, '{{')) {
            return $template;
        }

        return preg_replace_callback(
            self::PATTERN,
            fn (array $match): string => $this->value($match[1], $page),
            $template,
        ) ?? $template;
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

    private function value(string $key, Page $page): string
    {
        return match ($key) {
            'h1' => $page->getH1(),
            'excerpt' => $page->getSearchExcerpt() ?? '',
            'url' => $this->url($page),
            default => $this->mainImageUrl($page),
        };
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
