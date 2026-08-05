<?php

namespace Pushword\Admin\Tests\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Controller\PageCrudController;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\User;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * Unsaved page edits are kept in localStorage, which is per browser and outlives
 * the session. Two strings in the rendered page keep that copy the editor's own,
 * and losing either one is silent — the form still answers 200:
 *
 * - the recovery key carries the user id, so a shared machine never offers one
 *   editor's unsaved work to whoever signs in next;
 * - `window.pwLogoutPath` is how admin.js recognises the way out, and drops the
 *   copies when it is taken.
 *
 * @see \Pushword\Admin\Controller\DashboardController::configureAssets()
 */
#[Group('integration')]
final class PageEditUnsavedRecoveryTest extends AbstractAdminTestClass
{
    public function testTheRecoveryCopyIsKeyedByPageAndEditor(): void
    {
        $client = $this->loginUser();

        /** @var PageRepository $pageRepo */
        $pageRepo = self::getContainer()->get(PageRepository::class);
        $page = $pageRepo->findOneBy(['slug' => 'homepage', 'host' => 'localhost.dev']);
        self::assertInstanceOf(Page::class, $page);
        $pageId = $page->id;
        self::assertNotNull($pageId);

        /** @var UserRepository $userRepo */
        $userRepo = self::getContainer()->get(UserRepository::class);
        $user = $userRepo->findOneBy(['email' => 'admin@example.tld']);
        self::assertInstanceOf(User::class, $user);

        $crawler = $client->request(Request::METHOD_GET, $this->buildEditPath($pageId));
        self::assertResponseIsSuccessful();

        self::assertSame(
            'pw:unsaved:'.$user->id.':page:'.$pageId,
            $crawler->filter('form[data-pw-unsaved-key]')->attr('data-pw-unsaved-key'),
            'An unsaved copy not keyed by editor is offered to the next one signing in',
        );

        $html = (string) $client->getResponse()->getContent();

        self::assertStringContainsString(
            'window.pwLogoutPath="/logout"',
            $html,
            'Without it admin.js cannot tell signing out from any other link',
        );

        // Both wordings ship, or the banner falls back to its English default —
        // silently, and only in the case where a colleague's save is at stake.
        self::assertStringContainsString(
            'conflict: "',
            $html,
            'The edit form must publish a conflict wording beside the plain one',
        );
        self::assertStringNotContainsString(
            'conflict: "adminPageUnsavedChangesConflict"',
            $html,
            'The conflict wording is untranslated: the banner would show the key itself',
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
