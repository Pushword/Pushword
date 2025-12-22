<?php

namespace Pushword\Version\Tests;

use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Entity\Page;
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

        // Find a page dynamically instead of hardcoding ID 1
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $page = $em->getRepository(Page::class)->findOneBy(['slug' => 'homepage', 'host' => 'localhost.dev']);
        self::assertNotNull($page, 'Homepage should exist');
        /** @var int $pageId */
        $pageId = $page->getId();
        self::assertGreaterThan(0, $pageId, 'Page ID should be a positive integer');

        $client->request(Request::METHOD_GET, '/admin/version/'.$pageId.'/list');
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());

        $versionner = new Versionner(
            self::bootKernel()->getLogDir(),
            self::getContainer()->get('doctrine.orm.default_entity_manager'),
            new Serializer([], ['json' => new JsonEncoder()])
        );

        $pageVersions = $versionner->getPageVersions($pageId);
        $version = $pageVersions[0];

        $client->request(Request::METHOD_GET, '/admin/version/'.$pageId.'/'.$version);
        self::assertSame(Response::HTTP_FOUND, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());

        $client->request(Request::METHOD_GET, '/admin/version/'.$pageId.'/reset');
        self::assertSame(Response::HTTP_FOUND, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent()); // @phpstan-ignore-line
    }
}
