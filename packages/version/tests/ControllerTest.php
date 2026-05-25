<?php

namespace Pushword\Version\Tests;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Entity\Page;
use Pushword\Snippet\Entity\Snippet;
use Pushword\Version\Versionner;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

#[Group('integration')]
final class ControllerTest extends AbstractAdminTestClass
{
    public function testLogin(): void
    {
        $client = $this->loginUser();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $page = $em->getRepository(Page::class)->findOneBy(['host' => 'localhost.dev'])
            ?? $em->getRepository(Page::class)->findOneBy([]);
        self::assertNotNull($page, 'At least one page should exist');

        /** @var int $pageId */
        $pageId = $page->id;
        self::assertGreaterThan(0, $pageId);

        /** @var string $logDir */
        $logDir = self::getContainer()->getParameter('kernel.logs_dir');
        $versionner = new Versionner(
            $logDir,
            $em,
            new Serializer([], ['json' => new JsonEncoder()])
        );

        // Clear stale version files from other tests in the same ParaTest worker
        $versionner->reset('page', $pageId);

        // Update the page to trigger version creation via the Doctrine postUpdate listener
        $page->setTitle($page->getTitle().' (version test)');
        $em->flush();

        /** @var Router $router */
        $router = self::getContainer()->get('router');

        $pageVersions = $versionner->getVersions('page', $pageId);
        self::assertNotEmpty($pageVersions, 'Page should have at least one version');
        $version = $pageVersions[0];

        // Test admin routes (these require EasyAdmin context via admin dashboard)
        $listUrl = $router->generate('admin_version_list', ['type' => 'page', 'id' => $pageId]);
        $client->request(Request::METHOD_GET, $listUrl);
        self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());

        $loadUrl = $router->generate('admin_version_load', ['type' => 'page', 'id' => $pageId, 'version' => $version]);
        $client->request(Request::METHOD_GET, $loadUrl);
        self::assertSame(302, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());

        $resetUrl = $router->generate('admin_version_reset', ['type' => 'page', 'id' => $pageId]);
        $client->request(Request::METHOD_GET, $resetUrl);
        self::assertSame(302, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    }

    public function testSnippetVersionRoutes(): void
    {
        $client = $this->loginUser();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $snippet = new Snippet();
        $snippet->host = 'localhost.dev';
        $snippet->setSlug('version-route-'.uniqid());
        $snippet->setName('Snippet version route');
        $snippet->setContent('first');

        $em->persist($snippet);
        $em->flush();

        $snippet->setContent('second');
        $em->flush();

        /** @var Router $router */
        $router = self::getContainer()->get('router');

        /** @var string $logDir */
        $logDir = self::getContainer()->getParameter('kernel.logs_dir');
        $versionner = new Versionner($logDir, $em, self::getContainer()->get('serializer'));

        $versions = $versionner->getVersions('snippet', (int) $snippet->id);
        self::assertNotEmpty($versions, 'Snippet should have at least one version');

        $listUrl = $router->generate('admin_version_list', ['type' => 'snippet', 'id' => $snippet->id]);
        $client->request(Request::METHOD_GET, $listUrl);
        self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());

        $compareUrl = $router->generate('admin_version_compare', ['type' => 'snippet', 'id' => $snippet->id, 'versionLeft' => $versions[0], 'versionRight' => 'current']);
        $client->request(Request::METHOD_GET, $compareUrl);
        self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());

        $loadUrl = $router->generate('admin_version_load', ['type' => 'snippet', 'id' => $snippet->id, 'version' => $versions[0]]);
        $client->request(Request::METHOD_GET, $loadUrl);
        self::assertSame(302, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());

        $versionner->reset('snippet', (int) $snippet->id);
        $em->remove($em->getRepository(Snippet::class)->find($snippet->id) ?? $snippet);
        $em->flush();
    }

    public function testSnippetSaveCompareWritesEditedFields(): void
    {
        $client = $this->loginUser();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $snippet = new Snippet();
        $snippet->host = 'localhost.dev';
        $snippet->setSlug('save-compare-'.uniqid());
        $snippet->setName('Before');
        $snippet->setContent('before content');

        $em->persist($snippet);
        $em->flush();

        /** @var Router $router */
        $router = self::getContainer()->get('router');

        $saveUrl = $router->generate('admin_version_save_compare', ['type' => 'snippet', 'id' => $snippet->id]);
        $client->request(Request::METHOD_POST, $saveUrl, [
            'content' => 'after content',
            'name' => 'After',
            'slug' => $snippet->getSlug(),
        ]);
        self::assertSame(302, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());

        $em->clear();
        $updated = $em->getRepository(Snippet::class)->find($snippet->id);
        self::assertNotNull($updated);
        self::assertSame('after content', $updated->getContent());
        self::assertSame('After', $updated->getName());

        /** @var string $logDir */
        $logDir = self::getContainer()->getParameter('kernel.logs_dir');
        new Versionner($logDir, $em, self::getContainer()->get('serializer'))->reset('snippet', (int) $snippet->id);
        $em->remove($updated);
        $em->flush();
    }
}
