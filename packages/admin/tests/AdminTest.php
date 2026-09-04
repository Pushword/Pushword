<?php

namespace Pushword\Admin\Tests;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Controller\PageCheatSheetCrudController;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
final class AdminTest extends AbstractAdminTestClass
{
    public function testLogin(): void
    {
        $this->tearDown();
        $client = self::createClient();

        $client->request(Request::METHOD_GET, '/admin/');
        self::assertSame(Response::HTTP_FOUND, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
        $client->followRedirect();
        self::assertStringContainsString('Login', (string) $client->getResponse()->getContent());
    }

    public function testAdmins(): void
    {
        $client = $this->loginUser();

        $client->catchExceptions(false);

        $routes = [
            'admin_user_list' => [],
            'admin_user_create' => [],
            'admin_user_edit' => ['id' => 1],
            'admin_media_list' => [],
            'admin_media_create' => [],
            'admin_media_edit' => ['id' => 1],
            'admin_page_list' => [],
            'admin_page_create' => [],
            'admin_page_edit' => ['id' => 1],
            'admin_page_show' => ['id' => 1],
            'admin_cheatsheet_edit' => ['id' => 1],
        ];

        foreach ($routes as $route => $parameters) {
            $client->request(Request::METHOD_GET, $this->generateAdminUrl($route, $parameters));

            if ('admin_page_show' === $route) {
                self::assertTrue($client->getResponse()->isRedirection());
                $client->followRedirect();
            }

            self::assertResponseIsSuccessful();
        }

        $client->request(Request::METHOD_GET, $this->generateAdminUrl('admin_page_edit', ['id' => 2]));
        self::assertResponseIsSuccessful();

        $client->request(Request::METHOD_GET, '/admin/cheatsheet');
        if ($client->getResponse()->isSuccessful()) {
            $client->submitForm('Create cheatsheet');
        }

        self::assertTrue($client->getResponse()->isRedirection(), (string) $client->getResponse()->getContent());
        $client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public function testCheatsheetGetDoesNotCreatePage(): void
    {
        $client = $this->loginUser();
        $client->disableReboot();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $repository = self::getContainer()->get(PageRepository::class);
        $em->getConnection()->beginTransaction();

        try {
            $existing = $repository->findOneBy(['slug' => PageCheatSheetCrudController::CHEATSHEET_SLUG]);
            if ($existing instanceof Page) {
                $em->remove($existing);
                $em->flush();
            }

            $client->request(Request::METHOD_GET, '/admin/cheatsheet');

            self::assertResponseIsSuccessful();
            self::assertNull($repository->findOneBy(['slug' => PageCheatSheetCrudController::CHEATSHEET_SLUG]));

            $client->submitForm('Create cheatsheet');
            self::assertResponseRedirects();
            self::assertNotNull($repository->findOneBy(['slug' => PageCheatSheetCrudController::CHEATSHEET_SLUG]));
        } finally {
            $em->getConnection()->rollBack();
            $em->clear();
        }
    }
}
