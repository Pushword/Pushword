<?php

namespace Pushword\Snippet\Tests\Admin;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Snippet\Entity\Snippet;
use Symfony\Component\HttpFoundation\Request;

#[Group('integration')]
final class SnippetCrudControllerTest extends AbstractAdminTestClass
{
    public function testNewFormShipsSlugFromNameScript(): void
    {
        $client = $this->loginUser();

        $client->request(Request::METHOD_GET, '/admin/snippet/new');

        $response = $client->getResponse();
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $html = (string) $response->getContent();

        // The auto-slug script relies on these field name suffixes.
        self::assertStringContainsString('[name]', $html);
        self::assertStringContainsString('[slug]', $html);

        // The slug-from-name helper must be present on the creation page.
        self::assertStringContainsString('function slugify', $html);
    }

    public function testNewFormOffersAllHostsChoiceAndBlockEditor(): void
    {
        $client = $this->loginUser();

        $client->request(Request::METHOD_GET, '/admin/snippet/new');

        $response = $client->getResponse();
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $html = (string) $response->getContent();

        // Host is a native <select> offering the global "All hosts" option.
        $allHosts = self::getContainer()->get('translator')->trans('snippet.field.host.all');
        self::assertMatchesRegularExpression('/<select[^>]*name="[^"]*\[host\]"/', $html);
        self::assertStringContainsString($allHosts, $html);

        // Content reuses the page block editor (hidden textarea + holder).
        self::assertStringContainsString('data-editorjs', $html);
        self::assertStringContainsString('editorjs-holder', $html);
    }

    public function testIndexShowsHostColumnWithAllHostsLabel(): void
    {
        $client = $this->loginUser();
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $global = new Snippet();
        $global->host = ''; // "All hosts"
        $global->setSlug('index-global-'.uniqid());
        $global->setName('Index global snippet');
        $global->setContent('x');

        $em->persist($global);
        $em->flush();

        $client->request(Request::METHOD_GET, '/admin/snippet');
        $response = $client->getResponse();
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $html = (string) $response->getContent();
        $allHosts = self::getContainer()->get('translator')->trans('snippet.field.host.all');

        // The catalogue surfaces name + the host, rendering a global snippet as "All hosts".
        self::assertStringContainsString('Index global snippet', $html);
        self::assertStringContainsString($allHosts, $html);

        // The request cycle detaches $global; re-fetch before cleanup.
        $em->remove($em->getRepository(Snippet::class)->find($global->id) ?? $global);
        $em->flush();
    }
}
