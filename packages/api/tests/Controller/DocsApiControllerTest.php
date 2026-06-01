<?php

namespace Pushword\Api\Tests\Controller;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

#[Group('integration')]
final class DocsApiControllerTest extends WebTestCase
{
    public function testDocsIsPublicAndListsKnownRoutes(): void
    {
        $client = self::createClient();
        $client->request(Request::METHOD_GET, '/api/docs');

        self::assertResponseIsSuccessful();
        $body = $client->getResponse()->getContent();
        self::assertIsString($body);

        $doc = json_decode($body, true);
        self::assertIsArray($doc);
        self::assertSame('3.1.0', $doc['openapi'] ?? null);
        self::assertArrayHasKey('paths', $doc);
        self::assertIsArray($doc['paths']);
        $paths = array_keys($doc['paths']);
        foreach ([
            '/api/page/{host}/{slug}',
            '/api/page/preview',
            '/api/page/search',
            '/api/media',
            '/api/media/{filename}',
            '/api/redirection',
            '/api/snippet',
            '/api/conversation',
            '/api/notification',
        ] as $expected) {
            self::assertContains($expected, $paths, 'Missing path: '.$expected);
        }

        self::assertArrayHasKey('components', $doc);
        self::assertIsArray($doc['components']);
        self::assertArrayHasKey('schemas', $doc['components']);
        self::assertIsArray($doc['components']['schemas']);
        self::assertArrayHasKey('Page', $doc['components']['schemas']);
        self::assertArrayHasKey('Media', $doc['components']['schemas']);
    }
}
