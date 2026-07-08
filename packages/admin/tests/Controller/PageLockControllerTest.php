<?php

namespace Pushword\Admin\Tests\Controller;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;

#[Group('integration')]
final class PageLockControllerTest extends AbstractAdminTestClass
{
    public function testPingRequiresAuth(): void
    {
        $this->tearDown();
        $client = self::createClient();

        $client->request(Request::METHOD_POST, '/admin/page/1/lock/ping');

        self::assertResponseRedirects();
    }

    public function testPingAcquiresLockForEditor(): void
    {
        $data = $this->ping($this->loginUser(), 1, 'tab-test');

        self::assertTrue($data['acquired']);
        self::assertTrue($data['isOwner']);
        self::assertNotNull($data['lockInfo']);
        self::assertSame('tab-test', $data['lockInfo']['tabId']);
    }

    public function testPingWithoutBodyStillAcquires(): void
    {
        // The JS always posts a JSON body, but a body-less ping must not break:
        // $tabId falls back to null and the lock is still acquired.
        $data = $this->ping($this->loginUser(), 2, null);

        self::assertTrue($data['acquired']);
        self::assertNotNull($data['lockInfo']);
        self::assertNull($data['lockInfo']['tabId']);
    }

    public function testPingFromDifferentTabReportsSameUser(): void
    {
        $client = $this->loginUser();

        // First tab acquires the lock.
        self::assertTrue($this->ping($client, 3, 'tab-a')['acquired']);

        // Same user, second tab: cannot acquire, but is recognised as the same user.
        $second = $this->ping($client, 3, 'tab-b');
        self::assertFalse($second['acquired']);
        self::assertFalse($second['isOwner']);
        self::assertTrue($second['isSameUser']);
    }

    /**
     * Post a lock ping as the given client and return the decoded JSON payload.
     * A null $tabId sends an empty body, mirroring a request without JSON payload.
     *
     * @return array{acquired: bool, isOwner: bool, isSameUser: bool|null, lockInfo: array<string, mixed>|null}
     */
    private function ping(KernelBrowser $client, int $pageId, ?string $tabId): array
    {
        $client->request(
            Request::METHOD_POST,
            '/admin/page/'.$pageId.'/lock/ping',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_Requested_With' => 'XMLHttpRequest'],
            null !== $tabId ? (string) json_encode(['tabId' => $tabId]) : '',
        );

        self::assertResponseIsSuccessful();

        /** @var array{acquired: bool, isOwner: bool, isSameUser: bool|null, lockInfo: array<string, mixed>|null} $data */
        $data = json_decode((string) $client->getResponse()->getContent(), true);

        return $data;
    }
}
