<?php

declare(strict_types=1);

namespace Pushword\AdminBlockEditor\Twig;

use Pushword\Core\Repository\PageRepository;
use Twig\Attribute\AsTwigFunction;

final readonly class PageLinkExtension
{
    public function __construct(private PageRepository $pages)
    {
    }

    /** @return list<array{uri: string, title: string}> */
    #[AsTwigFunction('editorjs_page_links')]
    public function pageLinks(string $host = ''): array
    {
        // Fetch only the labels: hydrating every page also loads its main content.
        $query = $this->pages->createQueryBuilder('p')
            ->select('p.slug', 'p.h1')
            ->orderBy('p.slug', 'ASC')
            ->setMaxResults(30000);
        $this->pages->andHost($query, $host);

        /** @var list<array{slug: string, h1: string}> $rows */
        $rows = $query->getQuery()->getArrayResult();

        return array_map(static fn (array $page): array => [
            'uri' => '/'.('homepage' === $page['slug'] ? '' : $page['slug']),
            'title' => trim(strip_tags($page['h1'])),
        ], $rows);
    }
}
