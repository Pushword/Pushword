<?php

namespace Pushword\Search\Tests;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Search\Service\Indexer;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

#[Group('integration')]
final class SearchControllerTest extends WebTestCase
{
    public function testSearchJsonEndpointReturnsRankedHits(): void
    {
        $client = self::createClient();
        self::getContainer()->get(Indexer::class)->reindexHost('localhost.dev');

        $client->request(Request::METHOD_GET, '/en/search?q=welcome&format=json');

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($payload);
        self::assertArrayHasKey('hits', $payload);
        self::assertArrayHasKey('totalHits', $payload);
    }

    public function testSearchHtmlPageRendersWithQuery(): void
    {
        $client = self::createClient();
        self::getContainer()->get(Indexer::class)->reindexHost('localhost.dev');

        $crawler = $client->request(Request::METHOD_GET, '/en/search?q=welcome');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        self::assertSame('welcome', $crawler->filter('input[name="q"]')->attr('value'));
    }
}
