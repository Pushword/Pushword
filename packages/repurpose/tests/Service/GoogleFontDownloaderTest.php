<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Tests\Service;

use PHPUnit\Framework\TestCase;
use Pushword\Repurpose\Service\GoogleFontDownloader;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GoogleFontDownloaderTest extends TestCase
{
    public function testItSendsTheLegacyUserAgentAndReturnsTheTtfBytes(): void
    {
        $userAgents = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$userAgents): MockResponse {
            $headers = $options['normalized_headers'] ?? null;
            $userAgents[] = \is_array($headers) ? ($headers['user-agent'] ?? null) : null;

            return new MockResponse(str_contains($url, 'gstatic') ? 'ttf-bytes' : '@font-face { src: url(https://fonts.gstatic.com/s/a.ttf); }');
        });

        self::assertSame('ttf-bytes', new GoogleFontDownloader($client)->download('Gabarito', 700));
        self::assertSame([['User-Agent: Mozilla/4.0'], ['User-Agent: Mozilla/4.0']], $userAgents, 'the legacy UA makes css2 answer with TTF sources');
    }

    public function testATransportErrorIsNull(): void
    {
        $client = new MockHttpClient(static fn (): MockResponse => new MockResponse('', ['error' => 'offline']));

        self::assertNull(new GoogleFontDownloader($client)->download('Gabarito', 700));
    }

    public function testAStylesheetWithoutTtfSourceIsNull(): void
    {
        $client = new MockHttpClient(static fn (): MockResponse => new MockResponse('@font-face { src: url(https://fonts.gstatic.com/s/a.woff2); }'));

        self::assertNull(new GoogleFontDownloader($client)->download('Gabarito', 700));
    }

    public function testAnEmptyTtfBodyIsNull(): void
    {
        $client = new MockHttpClient(static fn (string $m, string $url): MockResponse => new MockResponse(
            str_contains($url, 'gstatic') ? '' : '@font-face { src: url(https://fonts.gstatic.com/s/a.ttf); }',
        ));

        self::assertNull(new GoogleFontDownloader($client)->download('Gabarito', 700));
    }
}
