<?php

declare(strict_types=1);

namespace Pushword\PageScanner\EventSubscriber;

use Pushword\Admin\Menu\AbstractRouteMenuWithHostsSubscriber;
use Pushword\Core\Site\SiteRegistry;

final readonly class AdminMenuItemSubscriber extends AbstractRouteMenuWithHostsSubscriber
{
    public function __construct(SiteRegistry $siteRegistry)
    {
        parent::__construct($siteRegistry, 'adminLabelCheckContent', 'fa fa-check-circle', 'admin_page_scanner', 400);
    }
}
