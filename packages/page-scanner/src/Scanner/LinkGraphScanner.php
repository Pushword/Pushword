<?php

namespace Pushword\PageScanner\Scanner;

use Override;
use Pushword\Core\Site\SiteRegistry;

use function Safe\preg_match_all;

use Symfony\Contracts\Service\ResetInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Collects the internal link graph from the DOM page-scan already renders.
 *
 * Only crawlable `<a href>` links count. That is not a filter we apply but a
 * property of the rendering: link() renders a <span data-rot> and
 * HtmlUnpublishedLink renders a <span>, so obfuscated and unpublished links
 * carry no href and never reach the graph.
 *
 * Edges are (host/slug) couples, normalized but NOT resolved: whether a target
 * is a real page is decided by {@see LinkGraphBuilder} against the scanned page
 * set, so media, static files and dead links drop out by node membership. This
 * scanner deliberately does not reuse LinkedDocsScanner's resolution, whose
 * `everChecked` short-circuit leaves `lastPageChecked` null on every slug seen
 * more than once — an edge built on it would record one inbound per target for
 * the whole corpus.
 *
 * Rides the scanner loop only to get the rendered HTML for free; it reports no
 * error and always returns an empty error list.
 */
final class LinkGraphScanner extends AbstractScanner implements ResetInterface
{
    private const string HREF_REGEX = '/<a\s[^>]*?href=(["\'])(?P<href>[^"\']*)\1/i';

    /** @var array<string, list<string>> */
    private array $edges = [];

    public function __construct(
        private readonly SiteRegistry $siteRegistry,
        TranslatorInterface $translator,
    ) {
        parent::__construct($translator);
    }

    /**
     * Edges accumulate across the whole scan loop, so a long-running process
     * (admin, worker, kernel reuse in tests) must clear them between scans.
     */
    #[Override]
    public function reset(): void
    {
        $this->edges = [];
    }

    /** @return array<string, list<string>> */
    public function getEdges(): array
    {
        return $this->edges;
    }

    #[Override]
    protected function run(): void
    {
        if ('' === $this->pageHtml) {
            return; // redirection or a page whose render failed: not a node
        }

        $source = $this->page->host.'/'.$this->page->getSlug();
        $targets = $this->edges[$source] ?? [];

        foreach ($this->extractHrefs() as $href) {
            $target = $this->resolveTarget($href);
            if (null === $target) {
                continue;
            }

            if ($target === $source) {
                continue;
            }

            if (\in_array($target, $targets, true)) {
                continue;
            }

            $targets[] = $target;
        }

        $this->edges[$source] = $targets;
    }

    /** @return list<string> */
    private function extractHrefs(): array
    {
        preg_match_all(self::HREF_REGEX, $this->pageHtml, $matches);

        /** @var list<string> */
        return $matches['href'];
    }

    /**
     * Normalize an href into a `host/slug` node key, or null when it cannot
     * designate a page of this installation.
     */
    private function resolveTarget(string $href): ?string
    {
        $href = trim($href);

        if ('' === $href || str_starts_with($href, '#') || str_starts_with($href, '//')) {
            return null;
        }

        if (str_starts_with($href, 'http')) {
            $host = parse_url($href, \PHP_URL_HOST);
            if (! \is_string($host) || ! $this->siteRegistry->isKnownHost($host)) {
                return null; // external
            }

            return $host.'/'.$this->normalizeSlug((string) parse_url($href, \PHP_URL_PATH));
        }

        if (! str_starts_with($href, '/')) {
            return null; // mailto:, tel:, relative links
        }

        return $this->page->host.'/'.$this->normalizeSlug($href);
    }

    private function normalizeSlug(string $path): string
    {
        $path = explode('#', explode('?', $path, 2)[0], 2)[0];

        return trim($path, '/') ?: 'homepage';
    }
}
