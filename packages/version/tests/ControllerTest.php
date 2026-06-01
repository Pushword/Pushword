<?php

namespace Pushword\Version\Tests;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\RevisionCalculator;
use Pushword\Snippet\Entity\Snippet;
use Pushword\Version\Versionner;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

#[Group('integration')]
final class ControllerTest extends AbstractAdminTestClass
{
    private function buildVersionner(string $storageDir, EntityManagerInterface $em, SerializerInterface $serializer): Versionner
    {
        return new Versionner($storageDir, $em, $serializer, new RevisionCalculator($serializer));
    }

    private function buildPlainSerializer(): Serializer
    {
        return new Serializer([new DateTimeNormalizer(), new ObjectNormalizer()], ['json' => new JsonEncoder()]);
    }

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

        /** @var string $storageDir */
        $storageDir = self::getContainer()->getParameter('pw.pushword_version.storage_dir');
        $versionner = $this->buildVersionner($storageDir, $em, $this->buildPlainSerializer());

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
        $listContent = (string) $client->getResponse()->getContent();
        self::assertSame(200, $client->getResponse()->getStatusCode(), $listContent);
        // The page_actions block must actually render (wrong block names are silently dropped).
        $editUrl = $router->generate('admin_page_edit', ['entityId' => $pageId]);
        self::assertStringContainsString($editUrl, $listContent, 'list page links back to the page edit screen');

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
        $snippet->setContent('init');

        $em->persist($snippet);
        $em->flush();

        /** @var Router $router */
        $router = self::getContainer()->get('router');

        /** @var string $storageDir */
        $storageDir = self::getContainer()->getParameter('pw.pushword_version.storage_dir');
        $versionner = $this->buildVersionner($storageDir, $em, self::getContainer()->get('serializer'));

        // ParaTest workers share the version storage dir but each has its own DB,
        // so a sibling worker may have written stale versions under the same id.
        // Reset before creating the snapshots this test asserts on.
        $versionner->reset('snippet', (int) $snippet->id);

        $snippet->setContent('first');
        $em->flush();

        $snippet->setContent('second');
        $em->flush();

        $versions = $versionner->getVersions('snippet', (int) $snippet->id);
        self::assertNotEmpty($versions, 'Snippet should have at least one version');

        $listUrl = $router->generate('admin_version_list', ['type' => 'snippet', 'id' => $snippet->id]);
        $client->request(Request::METHOD_GET, $listUrl);
        self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());

        $compareUrl = $router->generate('admin_version_compare', ['type' => 'snippet', 'id' => $snippet->id, 'versionLeft' => $versions[0], 'versionRight' => 'current']);
        $client->request(Request::METHOD_GET, $compareUrl);
        self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());

        $dataUrl = $router->generate('admin_version_data', ['type' => 'snippet', 'id' => $snippet->id, 'version' => $versions[0]]);
        $client->request(Request::METHOD_GET, $dataUrl);
        self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($payload);
        self::assertSame('first', $payload['content'], 'version_data returns the snippet content of the requested version');
        self::assertArrayHasKey('unmanagedPropertiesAsYaml', $payload, 'version_data exposes custom properties as YAML');

        $currentDataUrl = $router->generate('admin_version_data', ['type' => 'snippet', 'id' => $snippet->id, 'version' => 'current']);
        $client->request(Request::METHOD_GET, $currentDataUrl);
        self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        $currentPayload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($currentPayload);
        self::assertSame('second', $currentPayload['content'], 'version_data with `current` returns the live entity content');

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

        /** @var string $storageDir */
        $storageDir = self::getContainer()->getParameter('pw.pushword_version.storage_dir');
        $this->buildVersionner($storageDir, $em, self::getContainer()->get('serializer'))->reset('snippet', (int) $snippet->id);
        $em->remove($updated);
        $em->flush();
    }

    public function testSaveCompareWritesCustomPropertiesFromYaml(): void
    {
        $client = $this->loginUser();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $snippet = new Snippet();
        $snippet->host = 'localhost.dev';
        $snippet->setSlug('cp-compare-'.uniqid());
        $snippet->setName('CP');
        $snippet->setContent('content');
        $snippet->setCustomProperty('foo', 'bar');

        $em->persist($snippet);
        $em->flush();

        /** @var Router $router */
        $router = self::getContainer()->get('router');

        // The version snapshot exposes the existing custom property as YAML.
        $dataUrl = $router->generate('admin_version_data', ['type' => 'snippet', 'id' => $snippet->id, 'version' => 'current']);
        $client->request(Request::METHOD_GET, $dataUrl);
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($payload);
        self::assertIsString($payload['unmanagedPropertiesAsYaml']);
        self::assertStringContainsString('foo: bar', $payload['unmanagedPropertiesAsYaml']);

        // Saving from the compare view merges the edited YAML back into customProperties.
        $saveUrl = $router->generate('admin_version_save_compare', ['type' => 'snippet', 'id' => $snippet->id]);
        $client->request(Request::METHOD_POST, $saveUrl, [
            'content' => 'content',
            'name' => 'CP',
            'slug' => $snippet->getSlug(),
            'unmanagedPropertiesAsYaml' => "foo: baz\nnewKey: value",
        ]);
        self::assertSame(302, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());

        $em->clear();
        $updated = $em->getRepository(Snippet::class)->find($snippet->id);
        self::assertNotNull($updated);
        self::assertSame('baz', $updated->getCustomProperty('foo'), 'edited custom property is updated');
        self::assertSame('value', $updated->getCustomProperty('newKey'), 'new custom property is added');

        /** @var string $storageDir */
        $storageDir = self::getContainer()->getParameter('pw.pushword_version.storage_dir');
        $this->buildVersionner($storageDir, $em, self::getContainer()->get('serializer'))->reset('snippet', (int) $snippet->id);
        $em->remove($updated);
        $em->flush();
    }
}
