<?php

namespace Pushword\StaticGenerator\Generator;

use Override;
use Pushword\Core\Entity\Page;
use Pushword\Core\Twig\MediaExtension;

class PagesGenerator extends PageGenerator
{
    #[Override]
    public function generate(?string $host = null): void
    {
        parent::generate($host);

        $this->preloadMediaCache();
        $pages = $this->getPagesWithEagerLoading($this->app->getMainHost());

        foreach ($pages as $page) {
            $this->generatePage($page);
        }

        $this->finishCompression();
    }

    /**
     * Preload all Media entities into a cache to avoid N+1 queries during rendering.
     */
    private function preloadMediaCache(): void
    {
        /** @var MediaExtension $mediaExtension */
        $mediaExtension = static::getKernel()->getContainer()->get(MediaExtension::class);
        $mediaExtension->preloadMediaCache();
    }

    public function generatePageBySlug(string $slug, ?string $host = null): void
    {
        parent::generate($host);

        $pages = $this->getPageRepository()
            ->getPublishedPages($this->app->getMainHost(), ['slug', 'LIKE', $slug]);

        foreach ($pages as $page) {
            $this->generatePage($page);
        }

        $this->finishCompression();
    }

    /**
     * Fetch pages with eager loading of relations to avoid N+1 queries during static generation.
     *
     * @return Page[]
     */
    private function getPagesWithEagerLoading(string $host): array
    {
        return $this->getPageRepository()
            ->createQueryBuilder('p')
            ->leftJoin('p.parentPage', 'parent')->addSelect('parent')
            ->leftJoin('p.childrenPages', 'children')->addSelect('children')
            ->leftJoin('p.mainImage', 'mainImage')->addSelect('mainImage')
            ->andWhere('p.publishedAt IS NOT NULL')
            ->andWhere('p.publishedAt <= :now')
            ->setParameter('now', new \DateTime(), 'datetime')
            ->andWhere('p.host = :host')
            ->setParameter('host', $host)
            ->getQuery()
            ->getResult();
    }
}
