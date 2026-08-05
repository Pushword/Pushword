<?php

namespace Pushword\Admin\Tests\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Controller\PageCrudController;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * The markdown editor hangs on two strings in the rendered page, and losing either
 * one degrades in silence: the form still answers 200, the body just becomes a bare
 * textarea with no toolbar.
 *
 * - `data-editor="markdown"` on the body is what admin.js looks for before fetching
 *   Monaco at all, and what the bundle then scans for.
 * - `window.pwMonacoUrl` is where it fetches it from. Without it the loader falls
 *   back to the unversioned path, so an upgraded site keeps serving a cached bundle.
 *
 * @see \Pushword\Admin\FormField\PageMainContentField
 * @see \Pushword\Admin\Controller\DashboardController::configureAssets()
 */
#[Group('integration')]
final class PageEditMarkdownEditorTest extends AbstractAdminTestClass
{
    public function testTheBodyCarriesWhatTheMarkdownEditorNeeds(): void
    {
        $client = $this->loginUser();

        /** @var PageRepository $pageRepo */
        $pageRepo = self::getContainer()->get(PageRepository::class);
        // Scoped to the host that leaves admin_block_editor off: the fixture holds a
        // second `homepage` on admin-block-editor.test, whose body is an EditorJS
        // widget instead.
        $page = $pageRepo->findOneBy(['slug' => 'homepage', 'host' => 'localhost.dev']);
        self::assertInstanceOf(Page::class, $page);
        $pageId = $page->id;
        self::assertNotNull($pageId);

        $crawler = $client->request(Request::METHOD_GET, $this->buildEditPath($pageId));
        self::assertResponseIsSuccessful();

        $body = $crawler->filter('textarea#Page_mainContent');
        self::assertCount(1, $body, 'The edit form should render the body as a textarea');
        self::assertSame(
            'markdown',
            $body->attr('data-editor'),
            'Without data-editor the body stays a plain textarea and no editor loads',
        );

        $html = (string) $client->getResponse()->getContent();
        self::assertMatchesRegularExpression(
            '#window\.pwMonacoUrl="/bundles/pushwordadmin/monaco/app\.js\?v=\d+"#',
            $html,
            'The dashboard must publish a versioned URL for admin.js to fetch Monaco from',
        );
    }

    private function buildEditPath(int $pageId): string
    {
        /** @var AdminUrlGenerator $urlGenerator */
        $urlGenerator = clone self::getContainer()->get(AdminUrlGenerator::class);
        $editUrl = $urlGenerator
            ->unsetAll()
            ->setController(PageCrudController::class)
            ->setAction('edit')
            ->setEntityId($pageId)
            ->generateUrl();

        $parsed = parse_url($editUrl);
        $query = $parsed['query'] ?? '';

        return ($parsed['path'] ?? '/').('' !== $query ? '?'.$query : '');
    }
}
