<?php

namespace Pushword\Core\EventListener;

use Pushword\Core\Entity\PageInterface as Page;

class PageListener
{
    public function preRemove(Page $page)
    {
        // method_exists($page, 'getChildrenPages') &&
        if (! $page->getChildrenPages()->isEmpty()) {
            foreach ($page->getChildrenPages() as $cPage) {
                $cPage->setParentPage(null);
            }
        }
    }
}
