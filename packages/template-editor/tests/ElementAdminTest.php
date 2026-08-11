<?php

namespace Pushword\TemplateEditor\Tests;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\TemplateEditor\ElementRepository;
use Symfony\Component\HttpFoundation\Request;

#[Group('integration')]
final class ElementAdminTest extends AbstractAdminTestClass
{
    public function testAdmin(): void
    {
        $client = $this->loginUser();

        $client->catchExceptions(false);

        $client->request(Request::METHOD_GET, '/admin/template/list');
        self::assertResponseIsSuccessful();

        $repo = new ElementRepository(self::bootKernel()->getProjectDir().'/templates', [], false);
        $element = $repo->getAll()[0];

        $client->request(Request::METHOD_GET, '/admin/template/edit/'.$element->getEncodedPath()); // /pushword.piedweb.com/page/_content.html.twig
        self::assertResponseIsSuccessful();

        // These bundles sit at a stable URL under a long public max-age, and the
        // path is unauthenticated whatever the page requires — so an unstamped one
        // lets a CDN serve the previous release's editor for days after a deploy.
        $html = (string) $client->getResponse()->getContent();
        foreach (['pushwordadmin/admin.css', 'pushwordadmin/admin.js', 'pushwordadmin/monaco/app.js', 'pushwordadminblockeditor/admin-block-editor.js'] as $bundleAsset) {
            self::assertMatchesRegularExpression('#/bundles/'.preg_quote($bundleAsset, '#').'\?v=\d+#', $html);
        }
    }
}
