<?php

namespace Pushword\StaticGenerator\EventSubscriber;

use Pushword\Admin\Menu\AbstractRouteMenuItemSubscriber;

final readonly class AdminMenuItemSubscriber extends AbstractRouteMenuItemSubscriber
{
    public function __construct()
    {
        parent::__construct('Static Generator', 'fa fa-file-code', 'admin_static_generator', 300);
    }
}
