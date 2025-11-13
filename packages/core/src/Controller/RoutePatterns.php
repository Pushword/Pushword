<?php

declare(strict_types=1);

namespace Pushword\Core\Controller;

/**
 * Route pattern constants used in controller attributes.
 *
 * These patterns are centralized to ensure consistency and easier maintenance
 * across all route definitions in the application.
 */
final class RoutePatterns
{
    // Hostname pattern: domain.com or subdomain.domain.com
    public const string HOST = '^(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)([A-Za-z0-9]|[A-Za-z0-9][A-Za-z0-9]*[A-Za-z0-9])$';

    // Locale pattern: matches locale code like 'en', 'fr', 'en_US', 'en-US', 'fr_FR', 'fr-FR' with optional trailing slash
    public const string LOCALE = '[a-zA-Z]{2}([-_][a-zA-Z]+)?\/|';

    // Page slug patterns
    public const string SLUG = '[A-Za-z0-9_\/\.\-]*$';

    public const string SLUG_WITH_TRAILING = '[A-Za-z0-9_\/\.\-]*[A-Za-z0-9]+$';

    // Pager pattern
    public const string PAGER = '\d+';

    public const string PAGER_OPTIONAL = '(|\d+)';

    // Media file pattern
    public const string MEDIA = '[a-zA-Z0-9\-/\.]*';
}
