<?php

namespace Pushword\StaticGenerator\Generator;

use Override;

class PagesGenerator extends PageGenerator
{
    #[Override]
    public function generate(?string $host = null): void
    {
        parent::generate($host);

        $pages = $this->getPageRepository()
            ->getPublishedPages($this->app->getMainHost());

        foreach ($pages as $page) {
            $this->generatePage($page);
        }

        $this->finishCompression();
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
}
