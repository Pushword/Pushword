<?php

namespace Pushword\PageScanner\Scanner;

use Pushword\Core\Service\EditorNotice\TwigErrorMarker;

/**
 * Report content blocks whose Twig failed to render. Such a block degrades to an
 * invisible marker instead of a 500 (see TwigErrorMarker); this scanner surfaces
 * those markers back to the editor.
 */
final class TwigErrorScanner extends AbstractScanner
{
    protected function run(): void
    {
        if ('' === $this->pageHtml) {
            return;
        }

        foreach (TwigErrorMarker::extractMessages($this->pageHtml) as $message) {
            $this->addError($this->trans('page_scanTwigError').' <code>'.htmlspecialchars($message).'</code>');
        }
    }
}
