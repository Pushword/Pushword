<?php

namespace Pushword\StaticGenerator\Generator;

class PagesGenerator extends PageGenerator
{
    public function generate(?string $host = null): void
    {
        parent::generate($host);

        $pages = $this->getPageRepository()
            ->setHostCanBeNull($this->mustGetPagesWithoutHost)
            ->getPublishedPages($this->app->getMainHost());

        foreach ($pages as $page) {
            $this->generatePage($host, $page);
            //if ($page->getRealSlug()) $this->generateFeedFor($page);
        }
    }
}
