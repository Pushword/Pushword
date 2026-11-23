<?php

declare(strict_types=1);

namespace Pushword\Admin\Tests\Controller;

use App\Controller\CollaborationPocController;
use PHPUnit\Framework\Attributes\Group;
use Psr\Cache\CacheItemPoolInterface;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Entity\User;
use Pushword\Core\Repository\UserRepository;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

#[Group('integration')]
final class CollaborationPocTest extends AbstractAdminTestClass
{
    public function testBrowserAndTokenClientShareOneDemoRoom(): void
    {
        $client = $this->loginUser();
        $client->request(Request::METHOD_GET, '/admin/collaboration-poc');
        self::assertResponseRedirects();
        $crawler = $client->followRedirect();
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('[data-collaboration-poc]');
        $syncUrl = $form->attr('data-sync-url');
        $csrf = $form->attr('data-sync-token');
        self::assertIsString($syncUrl);
        self::assertIsString($csrf);

        $client->jsonRequest(Request::METHOD_POST, $syncUrl, ['after' => 0]);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $browser = ['HTTP_X_COLLABORATION_TOKEN' => $csrf];
        $browserUpdate = base64_encode('browser update');
        $client->jsonRequest(Request::METHOD_POST, $syncUrl, ['after' => 0, 'update' => $browserUpdate], $browser);
        self::assertResponseIsSuccessful();
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('no-store'));
        $this->assertUpdates(1, [$browserUpdate]);

        $client->jsonRequest(Request::METHOD_POST, $syncUrl, ['after' => 1, 'update' => $browserUpdate], $browser);
        self::assertResponseIsSuccessful();
        $this->assertUpdates(1, []);

        $api = str_replace('/admin/', '/api/', $syncUrl);
        $client->jsonRequest(Request::METHOD_POST, $api, ['after' => 0]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin@example.tld']);
        self::assertInstanceOf(User::class, $user);
        self::assertNotNull($user->apiToken);
        $agent = ['HTTP_AUTHORIZATION' => 'Bearer '.$user->apiToken];
        $agentUpdate = base64_encode('agent update');
        $client->jsonRequest(Request::METHOD_POST, $api, ['after' => 0, 'update' => $agentUpdate], $agent);
        self::assertResponseIsSuccessful();
        $this->assertUpdates(2, [$browserUpdate, $agentUpdate]);

        $client->jsonRequest(Request::METHOD_POST, $syncUrl, ['after' => 1], $browser);
        $this->assertUpdates(2, [$agentUpdate]);

        $client->request(Request::METHOD_GET, '/admin/collaboration-poc');
        $otherRoom = $client->followRedirect()->filter('[data-collaboration-poc]');
        $otherUrl = $otherRoom->attr('data-sync-url');
        self::assertIsString($otherUrl);
        self::assertNotSame($syncUrl, $otherUrl);
        $client->jsonRequest(Request::METHOD_POST, str_replace('/admin/', '/api/', $otherUrl), ['after' => 0], $agent);
        $this->assertUpdates(0, []);
    }

    public function testHoldsTheRoomLockAndReportsFailedStorageWithoutAcknowledging(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn(new CacheItem());
        $locks = new LockFactory(new InMemoryStore());
        $room = '1111111111111111';
        $otherWriter = $locks->createLock('collaboration_poc.'.$room);
        $cache->expects(self::once())->method('save')->willReturnCallback(static function () use ($otherWriter): bool {
            self::assertFalse($otherWriter->acquire());

            return false;
        });

        $request = Request::create('/sync', Request::METHOD_POST, content: '{"after":0,"update":"ZGF0YQ=="}');
        $response = new CollaborationPocController($cache, $locks)->agentSync($request, $room);
        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        self::assertSame(['error' => 'Could not store the update'], json_decode((string) $response->getContent(), true));
        self::assertTrue($otherWriter->acquire());
    }

    public function testBrowserSyncRejectsInvalidUpdatesAndACursorBeyondTheRoom(): void
    {
        $client = $this->loginUser();
        $client->request(Request::METHOD_GET, '/admin/collaboration-poc');

        $form = $client->followRedirect()->filter('[data-collaboration-poc]');
        $syncUrl = $form->attr('data-sync-url');
        $csrf = $form->attr('data-sync-token');
        self::assertIsString($syncUrl);
        self::assertIsString($csrf);
        $browser = ['HTTP_X_COLLABORATION_TOKEN' => $csrf];

        foreach ([['after' => -1], ['after' => '0'], ['after' => 0, 'update' => '#'], ['after' => 0, 'update' => '']] as $payload) {
            $client->jsonRequest(Request::METHOD_POST, $syncUrl, $payload, $browser);
            self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        }

        $client->jsonRequest(Request::METHOD_POST, $syncUrl, ['after' => 1, 'update' => base64_encode('ahead')], $browser);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);

        $client->jsonRequest(Request::METHOD_POST, $syncUrl, ['after' => 0, 'update' => str_repeat('A', 262_144)], $browser);
        self::assertResponseStatusCodeSame(Response::HTTP_REQUEST_ENTITY_TOO_LARGE);

        $client->jsonRequest(Request::METHOD_POST, $syncUrl, ['after' => 0], $browser);
        $this->assertUpdates(0, []);
    }

    /** @param list<string> $updates */
    private function assertUpdates(int $after, array $updates): void
    {
        self::assertNotNull($this->client);
        $payload = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame(['after' => $after, 'updates' => $updates], $payload);
    }
}
