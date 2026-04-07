<?php

declare(strict_types=1);

namespace Pushword\Core\Event;

/**
 * Catalog of all framework-level event names dispatched by Pushword.
 *
 * Use these constants when subscribing or dispatching events.
 */
final class PushwordEvents
{
    /** Dispatched before content filters are applied to a property. */
    public const string FILTER_BEFORE = 'pushword.entity_filter.before_filtering';

    /** Dispatched after content filters are applied to a property. */
    public const string FILTER_AFTER = 'pushword.entity_filter.after_filtering';

    /** Dispatched to collect admin menu items. */
    public const string ADMIN_MENU = 'pushword.admin.menu_items';

    /** Dispatched to customize admin form fields. */
    public const string ADMIN_LOAD_FIELD = 'pushword.admin.load_field';

    /** Dispatched before static generation starts for a host. */
    public const string STATIC_PRE_GENERATE = 'pushword.static.pre_generate';

    /** Dispatched after static generation completes for a host. */
    public const string STATIC_POST_GENERATE = 'pushword.static.post_generate';

    /** Dispatched before the search string is parsed in pages list. */
    public const string PAGES_LIST_SEARCH = 'pushword.pages_list.before_search';
}
