<?php

namespace Pushword\StaticGenerator\Generator;

class PagesGenerator extends PageGenerator
{
    public function generate(?string $host = null): void
    {
        parent::generate($host);

        $pages = $this->getPageRepository()
            ->getPublishedPages($this->app->getMainHost());

        foreach ($pages as $page) {
            $this->generatePage($page);
        }
    }

    public function generatePageBySlug(string $page, ?string $host = null): void
    {
        parent::generate($host);

        $pages = $this->getPageRepository()
            ->getPublishedPages($this->app->getMainHost(), ['slug', 'LIKE', $page]);

        foreach ($pages as $page) {
            $this->generatePage($page);
        }
    }
}
