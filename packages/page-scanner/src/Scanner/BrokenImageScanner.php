<?php

namespace Pushword\PageScanner\Scanner;

use Pushword\Core\Service\Markdown\BrokenImageComment;

/**
 * Report body images (`![](…)`) that failed to resolve at render time. Such a
 * missing media degrades to an invisible marker instead of a 500 (see
 * BrokenImageComment); this scanner surfaces those markers back to the editor.
 */
final class BrokenImageScanner extends AbstractScanner
{
    protected function run(): void
    {
        if ('' === $this->pageHtml) {
            return;
        }

        foreach (BrokenImageComment::extractSources($this->pageHtml) as $src) {
            $this->addError('<code>'.htmlspecialchars($src).'</code> '.$this->trans('page_scanBrokenImage'));
        }
    }
}
