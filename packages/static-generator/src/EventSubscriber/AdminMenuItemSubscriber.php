<?php

declare(strict_types=1);

namespace Pushword\StaticGenerator\EventSubscriber;

use Pushword\Admin\Menu\AbstractRouteMenuWithHostsSubscriber;
use Pushword\Core\Site\SiteRegistry;

final readonly class AdminMenuItemSubscriber extends AbstractRouteMenuWithHostsSubscriber
{
    public function __construct(SiteRegistry $siteRegistry)
    {
        parent::__construct($siteRegistry, 'Static Generator', 'fa fa-file-code', 'admin_static_generator', 300);
    }
}
