<?php

namespace Pushword\Core\Utils;

use Masterminds\HTML5\Elements;

/**
 * masterminds/html5 predates feDropShadow's addition to the HTML spec's SVG
 * element name adjustment table, so every parse + serialize round-trip
 * (TOC heading fix, DomCrawler-based minification) lowercases the tag while
 * restoring every other SVG name. Patch the library's public tables once per
 * process. Remove when https://github.com/Masterminds/html5-php/pull/267
 * is merged and released.
 */
final class Html5SvgCasePatch
{
    public static function apply(): void
    {
        Elements::$svg['feDropShadow'] ??= 1;

        // The case map carries no @var upstream, hence the runtime narrowing.
        $map = Elements::$svgCaseSensitiveElementMap;
        if (\is_array($map) && ! isset($map['fedropshadow'])) {
            $map['fedropshadow'] = 'feDropShadow';
            Elements::$svgCaseSensitiveElementMap = $map;
        }
    }
}
