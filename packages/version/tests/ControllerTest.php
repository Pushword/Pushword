<?php

namespace Pushword\Version\Tests;

use Pushword\Admin\Tests\AbstractAdminTest;
use Pushword\Version\Versionner;

class ControllerTest extends AbstractAdminTest
{
    public function testLogin()
    {
        $pageClass = 'App\Entity\Page';

        $client = $this->loginUser();

        $client->request('GET', '/admin/version/1/list');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        $versionner = new Versionner(
            self::$kernel->getLogDir(),
            $pageClass,
            self::$kernel->getContainer()->get('doctrine.orm.default_entity_manager'),
            self::$kernel->getContainer()->get('serializer')
        );

        $pageVersions = $versionner->getPageVersions(1);
        $version = $pageVersions[0];

        $client->request('GET', '/admin/version/1/'.$version);
        $this->assertEquals(302, $client->getResponse()->getStatusCode());

        $client->request('GET', '/admin/version/1/reset');
        $this->assertEquals(302, $client->getResponse()->getStatusCode());
    }
}
