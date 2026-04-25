<?php

declare(strict_types=1);

namespace Pushword\Admin\Tests\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Controller\PageCrudController;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Repository\PageRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
final class PageCloneTest extends AbstractAdminTestClass
{
    public function testClonePage(): void
    {
        $client = $this->loginUser();
        $client->catchExceptions(false);

        /** @var PageRepository $pageRepo */
        $pageRepo = self::getContainer()->get(PageRepository::class);
        $page = $pageRepo->findOneBy(['slug' => 'homepage']);
        self::assertNotNull($page, 'Fixture page "homepage" must exist');

        /** @var AdminUrlGenerator $urlGenerator */
        $urlGenerator = clone self::getContainer()->get(AdminUrlGenerator::class);
        $cloneUrl = $urlGenerator
            ->unsetAll()
            ->setController(PageCrudController::class)
            ->setAction('clonePage')
            ->setEntityId($page->id)
            ->generateUrl();

        // Parse path+query only (AdminUrlGenerator may include the host)
        $parsed = parse_url($cloneUrl);
        $query = $parsed['query'] ?? '';
        $path = ($parsed['path'] ?? '/').('' !== $query ? '?'.$query : '');

        $client->request(Request::METHOD_GET, $path);

        $location = $client->getResponse()->headers->get('Location') ?? '';
        self::assertSame(Response::HTTP_FOUND, $client->getResponse()->getStatusCode(), 'Location: '.$location.' | Body: '.$client->getResponse()->getContent());
        self::assertStringNotContainsString('login', $location);

        // Container is rebuilt after the HTTP request — fetch fresh instances
        /** @var PageRepository $pageRepo */
        $pageRepo = self::getContainer()->get(PageRepository::class);
        $em = self::getContainer()->get('doctrine.orm.entity_manager');

        $clone = $pageRepo->findOneBy(['slug' => 'homepage-copy']);
        self::assertNotNull($clone, 'Cloned page "homepage-copy" must exist. Redirect was to: '.$location);
        self::assertNull($clone->getPublishedAt(), 'Clone must be unpublished');
        self::assertCount(0, $clone->getTranslations(), 'Clone must have no translation links');
        self::assertStringContainsString((string) $clone->id, $location);

        // Clean up so the test is repeatable
        $em->remove($clone);
        $em->flush();
    }
}
