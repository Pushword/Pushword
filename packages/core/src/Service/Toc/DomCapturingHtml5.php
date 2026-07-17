<?php

namespace Pushword\Core\Service\Toc;

use DOMDocument;
use Masterminds\HTML5;
use Override;

/**
 * Keeps a reference to the last parsed document.
 *
 * `MarkupFixer` and `TocGenerator` both take markup as a string and parse it
 * internally, so generating a TOC parses the same (potentially huge) document
 * twice. Injecting this parser into `MarkupFixer` exposes the DOM it already
 * built — headings included, with their ids injected in place — so the second
 * parse can be reduced to a handful of heading tags.
 */
final class DomCapturingHtml5 extends HTML5
{
    public ?DOMDocument $lastDocument = null;

    /**
     * @param string       $string
     * @param array<mixed> $options
     */
    #[Override]
    public function loadHTML($string, array $options = []): DOMDocument
    {
        return $this->lastDocument = parent::loadHTML($string, $options);
    }
}
