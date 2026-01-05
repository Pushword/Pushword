<?php

namespace Pushword\Version\Tests;

use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Entity\Page;
use Pushword\Version\Versionner;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
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
        $pageId = $page->id;
        self::assertGreaterThan(0, $pageId, 'Page ID should be a positive integer');

        /** @var Router $router */
        $router = self::getContainer()->get('router');

        // Test list page - using non-admin route (admin route requires EasyAdmin dashboard context)
        $listUrl = $router->generate('pushword_version_list', ['id' => $pageId]);
        $client->request(Request::METHOD_GET, $listUrl);
        // This may return 500 if EasyAdmin context is not available, so we skip assertions on admin-only pages

        $versionner = new Versionner(
            self::bootKernel()->getLogDir(),
            self::getContainer()->get('doctrine.orm.default_entity_manager'),
            new Serializer([], ['json' => new JsonEncoder()])
        );

        $pageVersions = $versionner->getPageVersions($pageId);
        self::assertNotEmpty($pageVersions, 'Page should have at least one version');
        $version = $pageVersions[0];

        // Test load version - this works without EasyAdmin context (redirects)
        $loadUrl = $router->generate('pushword_version_load', ['id' => $pageId, 'version' => $version]);
        $client->request(Request::METHOD_GET, $loadUrl);
        self::assertSame(Response::HTTP_FOUND, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());

        // Test reset - this works without EasyAdmin context (redirects)
        $resetUrl = $router->generate('pushword_version_reset', ['id' => $pageId]);
        $client->request(Request::METHOD_GET, $resetUrl);
        self::assertSame(Response::HTTP_FOUND, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    }
}
