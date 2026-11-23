<?php

declare(strict_types=1);

namespace Pushword\Admin\Tests\Controller;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Entity\LiveEditingState;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;

#[Group('integration')]
final class PageDraftControllerTest extends AbstractAdminTestClass
{
    public function testSwitchToCollaborativeIsVisibleBeforeASave(): void
    {
        $client = $this->loginUser();
        $client->disableReboot();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $page = new Page();
        $page->host = 'localhost.dev';
        $page->h1 = 'Collaboration presence';
        $page->slug = 'presence-'.bin2hex(random_bytes(5));
        $page->mainContent = 'Saved body';

        $em->persist($page);
        $em->flush();

        $id = $page->id;
        self::assertNotNull($id);

        try {
            $crawler = $client->request(Request::METHOD_GET, $this->generateAdminUrl('admin_page_edit', ['id' => $id]));
            self::assertSelectorExists('.page-actions [data-pw-page-draft] button[type="button"]');
            $draft = $crawler->filter('[data-pw-page-draft]');
            $url = $draft->attr('data-url');
            $token = $draft->attr('data-token');
            $revision = $crawler->filter('[data-pw-page-revision]')->attr('value');
            self::assertIsString($url);
            self::assertIsString($token);
            self::assertIsString($revision);

            $headers = ['HTTP_X_COLLABORATION_TOKEN' => $token, 'HTTP_X_COLLABORATION_TAB' => bin2hex(random_bytes(16))];
            $ping = '/admin/page/'.$id.'/lock/ping';

            try {
                $client->jsonRequest(Request::METHOD_POST, $url, ['after' => 0], $headers + ['HTTP_IF_MATCH' => $revision]);
                self::assertResponseIsSuccessful();
                $client->jsonRequest(Request::METHOD_POST, $ping, ['tabId' => 'classic']);
                self::assertResponseIsSuccessful();
                $snapshot = json_decode((string) $client->getResponse()->getContent(), true);
                self::assertIsArray($snapshot);
                self::assertTrue($snapshot['collaborative']);
                self::assertSame([], $snapshot['participants']);
                self::assertSame($revision, $snapshot['revision']);
            } finally {
                $client->request(Request::METHOD_DELETE, $url, server: $headers);
            }

            self::assertResponseStatusCodeSame(204);
            $client->jsonRequest(Request::METHOD_POST, $ping, ['tabId' => 'classic']);
            $snapshot = json_decode((string) $client->getResponse()->getContent(), true);
            self::assertIsArray($snapshot);
            self::assertFalse($snapshot['collaborative']);

            $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin@example.tld']);
            self::assertNotNull($user?->apiToken);
            $client->jsonRequest(Request::METHOD_POST, '/api/pages/'.$id.'/draft', ['after' => 0], ['HTTP_AUTHORIZATION' => 'Bearer '.$user->apiToken]);
            self::assertResponseIsSuccessful();
            $client->jsonRequest(Request::METHOD_POST, $ping, ['tabId' => 'classic']);
            self::assertResponseIsSuccessful();
            $snapshot = json_decode((string) $client->getResponse()->getContent(), true);
            self::assertIsArray($snapshot);
            self::assertTrue($snapshot['collaborative']);
            self::assertSame([['id' => $user->id, 'name' => $user->username ?: 'admin', 'api' => true]], $snapshot['participants']);
        } finally {
            $state = $em->find(LiveEditingState::class, 'page:'.$id);
            if ($state instanceof LiveEditingState) {
                $em->remove($state);
            }

            $saved = $em->find(Page::class, $id);
            if ($saved instanceof Page) {
                $em->remove($saved);
            }

            $em->flush();
        }
    }

    public function testBrowserAndAgentUseTheSamePageDraft(): void
    {
        $client = $this->loginUser();
        $crawler = $client->request(Request::METHOD_GET, $this->generateAdminUrl('admin_page_edit', ['id' => 1]));
        self::assertResponseIsSuccessful();
        $draft = $crawler->filter('[data-pw-page-draft]');
        $url = $draft->attr('data-url');
        $token = $draft->attr('data-token');
        $revision = $crawler->filter('[data-pw-page-revision]')->attr('value');
        self::assertIsString($url);
        self::assertIsString($token);
        self::assertIsString($revision);

        $client->jsonRequest(Request::METHOD_POST, $url, ['after' => 0], ['HTTP_IF_MATCH' => $revision]);
        self::assertResponseStatusCodeSame(403);
        self::assertJsonStringEqualsJsonString('{"error":"invalid_token"}', (string) $client->getResponse()->getContent());
        $client->jsonRequest(Request::METHOD_POST, $url, ['after' => 0], ['HTTP_X_COLLABORATION_TOKEN' => $token, 'HTTP_IF_MATCH' => $revision]);
        self::assertResponseIsSuccessful();
        $browser = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($browser);
        self::assertIsString($browser['room']);
        self::assertArrayHasKey('baseline', $browser);

        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin@example.tld']);
        self::assertNotNull($user?->apiToken);
        $client->jsonRequest(Request::METHOD_POST, '/api/pages/1/draft', ['after' => 0], ['HTTP_AUTHORIZATION' => 'Bearer '.$user->apiToken]);
        self::assertResponseIsSuccessful();
        $response = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($response);
        self::assertSame($browser['room'], $response['room']);
        self::assertSame($revision, $response['revision']);
    }
}
