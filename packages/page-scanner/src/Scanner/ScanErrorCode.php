<?php

namespace Pushword\PageScanner\Scanner;

/**
 * The stable identity of a finding, independent of its wording and its locale.
 *
 * A message is translated and quotes the offending URL or tag, which makes it a poor
 * thing to name an error by: `errors_to_ignore` and `<!-- page-scanner-ignore: … -->`
 * both match these codes first. They are grouped by prefix (`link-`, `image-`, `todo-`,
 * `translation-`) so a wildcard can silence a whole family.
 *
 * A code is public API: once released, changing one breaks the ignore rules of every
 * site relying on it.
 */
enum ScanErrorCode: string
{
    /** The page did not render: a 5xx, an empty response, or a Twig error in its template. */
    case RenderError = 'render-error';

    /** A content block whose Twig failed and degraded to an invisible marker. */
    case TwigError = 'twig-error';

    case DateShortcode = 'date-shortcode';

    case ParentHost = 'parent-host';

    /** A body image whose media could not be resolved at render time. */
    case ImageNotFound = 'image-not-found';

    case ImageAltMissing = 'image-alt-missing';

    case LinkEmpty = 'link-empty';

    case LinkRelative = 'link-relative';

    case LinkNotFound = 'link-not-found';

    case LinkNotPublished = 'link-not-published';

    case LinkRedirection = 'link-redirection';

    case LinkNoindex = 'link-noindex';

    /** A `#anchor` naming no element of the page. */
    case LinkAnchor = 'link-anchor';

    /** An external URL answering an unexpected status. */
    case LinkStatus = 'link-status';

    /** An external URL not answering at all: DNS, timeout, TLS. */
    case LinkUnreachable = 'link-unreachable';

    /** A `mailto:`/`tel:` link left in clear, harvestable by a spam bot. */
    case LinkMailto = 'link-mailto';

    case TodoUnknownPage = 'todo-unknown-page';

    case TodoLinkWhenPublished = 'todo-link-when-published';

    case TodoDoWhenPublished = 'todo-do-when-published';

    case TranslationSameLocale = 'translation-same-locale';

    case TranslationDuplicateLocale = 'translation-duplicate-locale';
}
