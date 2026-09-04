<?php

namespace Pushword\Core\Tests\Service;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Service\SafeRemoteFileFetcher;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

final class SafeRemoteFileFetcherTest extends TestCase
{
    public function testFetchesAFileFromAPublicAddress(): void
    {
        $client = new MockHttpClient(new MockResponse('image-bytes', [
            'http_code' => 200,
            'primary_ip' => '93.184.216.34',
        ]));

        self::assertSame('image-bytes', new SafeRemoteFileFetcher($client)->fetch('https://93.184.216.34/image.png'));
    }

    public function testRejectsLocalFileUrls(): void
    {
        $fetcher = new SafeRemoteFileFetcher(new MockHttpClient());

        $this->expectException(InvalidArgumentException::class);
        $fetcher->fetch('file:///etc/passwd');
    }

    public function testRejectsPrivateNetworkAddresses(): void
    {
        $fetcher = new SafeRemoteFileFetcher(new MockHttpClient());

        $this->expectException(TransportExceptionInterface::class);
        $fetcher->fetch('http://127.0.0.1/admin');
    }
}
