<?php

namespace Pushword\Version\Tests;

use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Version\Versionner;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

class ControllerTest extends AbstractAdminTestClass
{
    public function testLogin(): void
    {
        $client = $this->loginUser();

        $client->request(Request::METHOD_GET, '/admin/version/1/list');
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());

        $versionner = new Versionner(
            self::bootKernel()->getLogDir(),
            self::getContainer()->get('doctrine.orm.default_entity_manager'),
            new Serializer([], ['json' => new JsonEncoder()])
        );

        $pageVersions = $versionner->getPageVersions(1);
        $version = $pageVersions[0];

        $client->request(Request::METHOD_GET, '/admin/version/1/'.$version);
        self::assertSame(Response::HTTP_FOUND, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());

        $client->request(Request::METHOD_GET, '/admin/version/1/reset');
        self::assertSame(Response::HTTP_FOUND, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent()); // @phpstan-ignore-line
    }
}
