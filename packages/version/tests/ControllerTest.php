<?php

namespace Pushword\Version\Tests;

use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Version\Versionner;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

class ControllerTest extends AbstractAdminTestClass
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
            new Serializer([], ['json' => new JsonEncoder()])
        );

        $pageVersions = $versionner->getPageVersions(1);
        $version = $pageVersions[0];

        $client->request('GET', '/admin/version/1/'.$version);
        $this->assertEquals(302, $client->getResponse()->getStatusCode());

        $client->request('GET', '/admin/version/1/reset');
        $this->assertEquals(302, $client->getResponse()->getStatusCode());
    }
}
