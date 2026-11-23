<?php

declare(strict_types=1);

namespace Pushword\Admin\Tests\Controller;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;

#[Group('integration')]
final class PageDraftControllerTest extends AbstractAdminTestClass
{
    public function testBrowserAndAgentUseTheSamePageDraft(): void
    {
        $update = base64_encode(random_bytes(8));
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

        $client->jsonRequest(Request::METHOD_POST, $url, ['after' => 0, 'update' => $update], ['HTTP_IF_MATCH' => $revision]);
        self::assertResponseStatusCodeSame(403);
        self::assertJsonStringEqualsJsonString('{"error":"invalid_token"}', (string) $client->getResponse()->getContent());
        $client->jsonRequest(Request::METHOD_POST, $url, ['after' => 0, 'update' => $update], ['HTTP_X_COLLABORATION_TOKEN' => $token, 'HTTP_IF_MATCH' => $revision]);
        self::assertResponseIsSuccessful();

        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin@example.tld']);
        self::assertNotNull($user?->apiToken);
        $client->jsonRequest(Request::METHOD_POST, '/api/pages/1/draft', ['after' => 0], ['HTTP_AUTHORIZATION' => 'Bearer '.$user->apiToken]);
        self::assertResponseIsSuccessful();
        $response = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($response);
        self::assertSame([$update], $response['updates']);
        self::assertSame($revision, $response['revision']);
    }
}
