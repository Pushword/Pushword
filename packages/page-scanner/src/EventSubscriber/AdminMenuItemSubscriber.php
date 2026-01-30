<?php

namespace Pushword\PageScanner\EventSubscriber;

use Pushword\Admin\Menu\AbstractRouteMenuItemSubscriber;

final readonly class AdminMenuItemSubscriber extends AbstractRouteMenuItemSubscriber
{
    public function __construct()
    {
        parent::__construct('adminLabelCheckContent', 'fa fa-check-circle', 'admin_page_scanner', 400);
    }
}
