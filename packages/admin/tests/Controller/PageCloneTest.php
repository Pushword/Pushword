<?php

namespace Pushword\Admin\Tests\Controller;

use PHPUnit\Framework\Attributes\Group;
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

        /** @var PageRepository $pageRepo */
        $pageRepo = self::getContainer()->get(PageRepository::class);
        $page = $pageRepo->findOneBy(['slug' => 'homepage', 'host' => 'localhost.dev']);
        self::assertNotNull($page, 'Fixture page "homepage" must exist');

        $path = self::getContainer()->get('router')->generate('admin_page_clone_page', ['entityId' => $page->id]);
        $crawler = $client->request(Request::METHOD_GET, $this->generateAdminUrl('admin_page_list'));
        $token = $crawler->filter('form[action$="'.$path.'"] input[name="_token"]')->attr('value');
        self::assertNotNull($token);

        $client->request(Request::METHOD_GET, $path);
        self::assertContains($client->getResponse()->getStatusCode(), [Response::HTTP_NOT_FOUND, Response::HTTP_METHOD_NOT_ALLOWED]);

        $client->request(Request::METHOD_POST, $path, ['_token' => 'invalid']);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $client->request(Request::METHOD_POST, $path, ['_token' => $token]);

        $location = $client->getResponse()->headers->get('Location') ?? '';
        self::assertSame(Response::HTTP_FOUND, $client->getResponse()->getStatusCode(), 'Location: '.$location.' | Body: '.$client->getResponse()->getContent());
        self::assertStringNotContainsString('login', $location);

        // Container is rebuilt after the HTTP request — fetch fresh instances
        /** @var PageRepository $pageRepo */
        $pageRepo = self::getContainer()->get(PageRepository::class);
        $em = self::getContainer()->get('doctrine.orm.entity_manager');

        $clone = $pageRepo->findOneBy(['slug' => 'homepage-copy']);
        self::assertNotNull($clone, 'Cloned page "homepage-copy" must exist. Redirect was to: '.$location);
        self::assertNull($clone->publishedAt, 'Clone must be unpublished');
        self::assertCount(0, $clone->translations, 'Clone must have no translation links');
        self::assertStringContainsString((string) $clone->id, $location);

        // Clean up so the test is repeatable
        $em->remove($clone);
        $em->flush();

        $em->clear();

        $kitchenSink = $pageRepo->findOneBy(['slug' => 'kitchen-sink', 'host' => 'localhost.dev']);
        self::assertNotNull($kitchenSink);
        self::assertSame('homepage', $kitchenSink->parentPage?->slug, 'Removing the clone must not detach the original page children');
    }
}
