<?php

namespace Pushword\Core\Service;

use InvalidArgumentException;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class SafeRemoteFileFetcher
{
    private const int MAX_BYTES = 25_000_000;

    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = new NoPrivateNetworkHttpClient($httpClient);
    }

    public function fetch(string $url): string
    {
        $parts = parse_url($url);
        if (
            false === $parts
            || ! isset($parts['scheme'], $parts['host'])
            || ! \in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new InvalidArgumentException('Only public HTTP or HTTPS URLs are allowed.');
        }

        $response = $this->httpClient->request('GET', $url, [
            'max_redirects' => 3,
            'timeout' => 10,
            'max_duration' => 20,
            'no_proxy' => '*',
            'headers' => ['Accept' => 'image/*,application/octet-stream;q=0.8'],
            'on_progress' => static function (int $downloaded, int $downloadSize): void {
                if ($downloaded > self::MAX_BYTES || $downloadSize > self::MAX_BYTES) {
                    throw new InvalidArgumentException('The remote file is too large.');
                }
            },
        ]);

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw new InvalidArgumentException('The remote server did not return a file.');
        }

        $content = $response->getContent();
        if ('' === $content) {
            throw new InvalidArgumentException('The remote file is empty.');
        }

        if (\strlen($content) > self::MAX_BYTES) {
            throw new InvalidArgumentException('The remote file is too large.');
        }

        return $content;
    }
}
