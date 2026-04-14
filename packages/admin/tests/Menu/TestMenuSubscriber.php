<?php

namespace Pushword\Admin\Tests\Menu;

use Pushword\Admin\Menu\AbstractRouteMenuWithHostsSubscriber;
use Pushword\Core\Site\SiteRegistry;

final readonly class TestMenuSubscriber extends AbstractRouteMenuWithHostsSubscriber
{
    public function __construct(SiteRegistry $siteRegistry)
    {
        parent::__construct($siteRegistry, 'Test Menu', 'fa fa-test', 'test_route', 300);
    }
}
