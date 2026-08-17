<?php

namespace Pushword\LinkImprover;

use Pushword\Core\Cache\RenderEpoch;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The keyword → URL map the improver consumes, derived from the pages
 * themselves: every published page of a host offers each line of its `name`
 * as a linkable keyword. `Page::$name` was designed for this — line 1 is the
 * displayed name (breadcrumb, listings), further lines are link-only variants,
 * and the `*` wildcard (up to 10 characters) is understood.
 *
 * Built once per host+locale and memoized for the process (a pw:static run
 * renders every page against one query); reset per request in worker mode.
 */
final class InternalLinkSources implements ResetInterface
{
    /** @var array<string, list<array{0: string, 1: string}>> */
    private array $rows = [];

    /** @var array<string, string> */
    private array $epochs = [];

    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly SiteRegistry $apps,
        private readonly RenderEpoch $renderEpoch,
    ) {
    }

    /**
     * `[url, keywords]` rows for every published page of the host+locale,
     * ordered by slug so the map never depends on insertion order.
     *
     * @return list<array{0: string, 1: string}>
     */
    public function getRows(string $host, string $locale): array
    {
        $key = $host.'|'.$locale;
        $epoch = $this->renderEpoch->get($host);
        if (($this->epochs[$key] ?? null) !== $epoch) {
            unset($this->rows[$key]);
            $this->epochs[$key] = $epoch;
        }

        return $this->rows[$key] ??= $this->buildRows($host, $locale);
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function buildRows(string $host, string $locale): array
    {
        $queryBuilder = $this->pageRepository->getPublishedPageQueryBuilder($host);
        $this->pageRepository->andNotRedirection($queryBuilder);
        $this->pageRepository->andLocale($queryBuilder, $locale);

        /** @var list<array{slug: string, name: string}> $pages */
        $pages = $queryBuilder->select('p.slug', 'p.name')
            ->andWhere("p.name != ''")
            ->orderBy('p.slug', 'ASC')
            ->getQuery()->getArrayResult();

        $rows = [];
        foreach ($pages as $page) {
            $keywords = self::keywords($page['name']);
            if ([] === $keywords) {
                continue;
            }

            $rows[] = [self::url($page['slug']), implode(',', $keywords)];
        }

        return $rows;
    }

    /** The homepage answers at the root, not at `/homepage`. */
    public static function url(string $slug): string
    {
        return 'homepage' === $slug ? '/' : '/'.$slug;
    }

    /**
     * Newlines separate a page's names; commas separate too, as they do in the
     * engine's own CSV format.
     *
     * @return list<string>
     */
    public static function keywords(string $name): array
    {
        $parts = preg_split('/[\n,]/', $name);
        \assert(false !== $parts);

        $keywords = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ('' !== $part) {
                $keywords[] = $part;
            }
        }

        return $keywords;
    }

    public function reset(): void
    {
        foreach ($this->apps->getAll() as $site) {
            if ($site->isStatic) {
                return;
            }
        }

        $this->rows = [];
        $this->epochs = [];
    }
}
