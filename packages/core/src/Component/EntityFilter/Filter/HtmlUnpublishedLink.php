<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\Component\EntityFilter\Attribute\AsFilter;
use Pushword\Core\Component\EntityFilter\Manager;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;

/**
 * Hide links pointing to pages that are not yet published.
 * Converts <a href="...">text</a> to
 * <span title="..." data-status="unpublished" data-href="...">text</span>
 * so the text stays visible, the URL is not clickable for visitors, and a
 * tooltip helps debugging. A small JS snippet restores the <a> for logged-in
 * editors using the original data-href.
 */
#[AsFilter]
final readonly class HtmlUnpublishedLink implements FilterInterface
{
    public const string HTML_REGEX = '/<a(?P<before>\s+[^>]*?)href=(?P<quote>["\'])(?P<href>[^"\']+)(?P=quote)(?P<after>[^>]*)>(?P<content>(?:(?!<\/a>).)*)<\/a>/is';

    public const string UNPUBLISHED_TITLE = 'Page en cours de publication';

    public function __construct(
        private PageRepository $pageRepository,
    ) {
    }

    public function apply(mixed $propertyValue, Page $page, Manager $manager, string $property = ''): mixed
    {
        assert(is_scalar($propertyValue));
        $body = (string) $propertyValue;

        if (! str_contains($body, '<a ')) {
            return $body;
        }

        return preg_replace_callback(
            self::HTML_REGEX,
            fn (array $match): string => $this->maybeMaskLink($match, $page),
            $body
        ) ?? $body;
    }

    /**
     * @param array<int|string, string> $match
     */
    private function maybeMaskLink(array $match, Page $currentPage): string
    {
        $href = $match['href'];
        $target = $this->resolveTargetPage($href, $currentPage);

        if (null === $target || $target->isPublished()) {
            return $match[0];
        }

        return '<span title="'.self::UNPUBLISHED_TITLE.'" data-status="unpublished" data-href="'.htmlspecialchars($href, \ENT_QUOTES | \ENT_HTML5).'">'.$match['content'].'</span>';
    }

    private function resolveTargetPage(string $href, Page $currentPage): ?Page
    {
        if ('' === $href
            || str_starts_with($href, '#')
            || str_starts_with($href, 'mailto:')
            || str_starts_with($href, 'tel:')
            || str_starts_with($href, 'javascript:')
            || str_starts_with($href, 'data:')) {
            return null;
        }

        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            $parts = parse_url($href);
            $host = $parts['host'] ?? '';
            if ('' === $host) {
                return null;
            }

            $path = $parts['path'] ?? '';
        } elseif (str_starts_with($href, '/')) {
            $host = $currentPage->host;
            $path = $href;
        } else {
            return null;
        }

        $slug = $this->extractSlug($path);

        return $this->pageRepository->getPageBySlug($slug, $host);
    }

    private function extractSlug(string $path): string
    {
        $path = (string) strtok($path, '#?');
        $slug = trim($path, '/');

        return '' !== $slug ? $slug : 'homepage';
    }
}
