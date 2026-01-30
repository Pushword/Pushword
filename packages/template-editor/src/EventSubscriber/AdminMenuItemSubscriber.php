<?php

namespace Pushword\TemplateEditor\EventSubscriber;

use Pushword\Admin\Menu\AbstractRouteMenuItemSubscriber;

final readonly class AdminMenuItemSubscriber extends AbstractRouteMenuItemSubscriber
{
    public function __construct()
    {
        parent::__construct('Template Editor', 'fa fa-code', 'admin_template_editor_list', 200);
    }
}
