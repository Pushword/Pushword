<?php

namespace Pushword\Admin\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Controller\PageCrudController;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Component\HttpFoundation\Request;

/**
 * `Page::$locale` defaults to '' and pages imported by the flat sync or written
 * through the API keep it that way. The edit form must show the locale that is
 * actually in effect (the page's host's), and must never render `required` on it:
 * the field lives in a collapsed fieldset, where an invalid control makes EasyAdmin
 * cancel the submit with no visible feedback at all — the save button goes mute.
 *
 * @see \Pushword\Admin\FormField\PageLocaleField
 */
#[Group('integration')]
final class PageEditLocaleFieldTest extends AbstractAdminTestClass
{
    public function testEmptyLocaleIsPrefilledFromTheHostAndNeverRequired(): void
    {
        $client = $this->loginUser();

        /** @var PageRepository $pageRepo */
        $pageRepo = self::getContainer()->get(PageRepository::class);
        $page = $pageRepo->findOneBy(['slug' => 'homepage']);
        self::assertInstanceOf(Page::class, $page);
        $pageId = $page->id;
        self::assertNotNull($pageId);

        $page->locale = '';
        $this->getEntityManager()->flush();

        /** @var SiteRegistry $apps */
        $apps = self::getContainer()->get(SiteRegistry::class);
        $expectedLocale = $apps->get($page->host)->getLocale();
        self::assertNotSame('', $expectedLocale, 'The test site must declare a locale');

        $crawler = $client->request(Request::METHOD_GET, $this->buildEditPath($pageId));
        self::assertResponseIsSuccessful();

        $localeInput = $crawler->filter('input#Page_locale');
        self::assertCount(1, $localeInput, 'The edit form should render the locale field');

        self::assertSame(
            $expectedLocale,
            $localeInput->attr('value'),
            "An empty locale must be shown as the host's locale, not as an empty box",
        );

        self::assertNull(
            $localeInput->attr('required'),
            'A required locale in a collapsed fieldset silently blocks the whole form',
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

    private function getEntityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
