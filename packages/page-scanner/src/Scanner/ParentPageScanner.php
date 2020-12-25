<?php

namespace Pushword\PageScanner\Scanner;

use Pushword\Core\Entity\Page;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Permit to find error in image or link.
 */
final class ParentPageScanner extends AbstractScanner
{
    public function __construct(
        TranslatorInterface $translator,
    ) {
        parent::__construct($translator);
    }

    protected function run(): void
    {
        $this->checkParentPageHost($this->page);
    }

    private function checkParentPageHost(Page $Page): void
    {
        $parent = $Page->getParentPage();
        if (! $parent instanceof Page) {
            return;
        }

        if ('' === $parent->getHost()) {
            return;
        }

        if ('' === $Page->getHost()) {
            return;
        }

        if ($Page->getHost() === $parent->getHost()) {
            return;
        }

        $this->addError($this->trans('page_scan.different_host')
            .' : <code>'.$parent->getHost().'</code> vs <code>'.$Page->getHost().'</code>');
    }
}
