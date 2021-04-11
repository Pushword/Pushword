<?php

namespace Pushword\PageScanner\Scanner;

use Pushword\Core\Entity\PageInterface;

/**
 * Permit to find error in image or link.
 */
final class ParentPageScanner extends AbstractScanner
{
    protected function run(): void
    {
        $this->checkParentPageHost($this->page);
    }

    private function checkParentPageHost(Pageinterface $page): void
    {
        $parent = $page->getParentPage();

        if ($parent && $parent->getHost() && $page->getHost() && $page->getHost() != $parent->getHost()) {
            $this->addError($this->trans('page_scan.different_host')
                .' : <code>'.$parent->getHost().'</code> vs <code>'.$page->getHost().'</code>');
        }
    }
}
