<?php

namespace Pushword\PageScanner\Scanner;

use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @phpstan-type UrlCheckResult true|array{code: string, message: string}
 */
final class ParallelUrlChecker
{
    private const string DEFAULT_USER_AGENT = 'Mozilla/5.0 (Linux; Android 11; Pixel 5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.91 Mobile Safari/537.36';

    /**
     * The stored shape is part of the cache key: an entry written before findings
     * carried a code is a bare message, which nothing downstream can read now.
     */
    private const string CACHE_PREFIX = 'url.v2.';

    /** @var array<string, UrlCheckResult> */
    private array $results = [];

    private readonly HttpClientInterface $httpClient;

    public function __construct(
        private readonly CacheInterface $externalUrlCache,
        private readonly TranslatorInterface $translator,
        HttpClientInterface $httpClient,
        private readonly int $externalUrlCacheTtl = 86400,
        private readonly int $externalUrlFailureCacheTtl = 3600,
        private readonly int $parallelBatchSize = 50,
        private readonly int $urlCheckTimeoutMs = 10000,
    ) {
        $this->httpClient = new NoPrivateNetworkHttpClient($httpClient);
    }

    /**
     * Check multiple URLs in parallel using lazy HTTP responses.
     *
     * @param string[] $urls
     * @param bool     $recheck ask the network again, whatever the pool holds
     *
     * @return array<string, UrlCheckResult> URL => true or the finding it failed with
     */
    public function checkUrls(array $urls, bool $recheck = false): array
    {
        $urls = array_unique($urls);
        $this->results = [];

        $uncachedUrls = [];
        foreach ($urls as $url) {
            if ($recheck) {
                // Dropped rather than ignored: the write below goes through get(),
                // which keeps whatever the pool already holds for that key.
                $this->clearCacheFor($url);
                $uncachedUrls[] = $url;

                continue;
            }

            // `$save = false` or the probe stores its own miss: the pool then answers
            // every later get() with that null, callback included, and the result of
            // the check below is never written — nothing was ever cached.
            /** @var UrlCheckResult|null $cached */
            $cached = $this->externalUrlCache->get(self::cacheKey($url), static function (ItemInterface $item, bool &$save): null {
                $save = false;

                return null;
            });

            if (null !== $cached) {
                $this->results[$url] = $cached;
            } else {
                $uncachedUrls[] = $url;
            }
        }

        $batches = array_chunk($uncachedUrls, max(1, $this->parallelBatchSize));
        foreach ($batches as $batch) {
            $this->checkBatch($batch);
        }

        return $this->results;
    }

    /**
     * @param string[] $urls
     */
    private function checkBatch(array $urls): void
    {
        foreach ($this->probe($urls) as $url => $result) {
            $this->results[$url] = $result;
            $this->cacheResult($url, $result);
        }
    }

    /** @return UrlCheckResult */
    public function checkUrlUncached(string $url): true|array
    {
        return $this->probe([$url])[$url];
    }

    /**
     * @param string[] $urls
     *
     * @return array<string, UrlCheckResult>
     */
    private function probe(array $urls): array
    {
        /** @var array<string, ResponseInterface> $responses */
        $responses = [];
        $results = [];

        foreach ($urls as $url) {
            try {
                $responses[$url] = $this->httpClient->request('HEAD', $url, [
                    'headers' => ['User-Agent' => self::DEFAULT_USER_AGENT],
                    'max_duration' => $this->urlCheckTimeoutMs / 1000,
                    'max_redirects' => 0,
                    'no_proxy' => '*',
                    'timeout' => min(5, $this->urlCheckTimeoutMs / 1000),
                ]);
            } catch (TransportExceptionInterface $exception) {
                $results[$url] = $this->unreachable($exception->getMessage());
            }
        }

        foreach ($responses as $url => $response) {
            try {
                $httpCode = $response->getStatusCode();
                $results[$url] = \in_array($httpCode, [200, 206, 403, 410], true)
                    ? true
                    : [
                        'code' => ScanErrorCode::LinkStatus->value,
                        'message' => $this->translator->trans('page_scanStatusCode').' ('.$httpCode.')',
                    ];
            } catch (TransportExceptionInterface $exception) {
                $results[$url] = $this->unreachable($exception->getMessage());
            } finally {
                $response->cancel();
            }
        }

        return $results;
    }

    /** @return array{code: string, message: string} */
    private function unreachable(string $message): array
    {
        return [
            'code' => ScanErrorCode::LinkUnreachable->value,
            'message' => $this->translator->trans('page_scanUnreachable', ['errorMessage' => $message]),
        ];
    }

    /**
     * @param UrlCheckResult $result
     */
    private function cacheResult(string $url, true|array $result): void
    {
        $this->externalUrlCache->get(self::cacheKey($url), function (ItemInterface $item) use ($result): true|array {
            $item->expiresAfter(self::ttlFor($result, $this->externalUrlCacheTtl, $this->externalUrlFailureCacheTtl));

            return $result;
        });
    }

    /**
     * A failure is held for less time than a success: a link fixed this morning should
     * stop being reported this morning, and a scan run while the network is down would
     * otherwise report every external URL of the corpus as dead until tomorrow.
     *
     * Capped by the success TTL, so that turning the cache off (`0`) turns it off for
     * findings too.
     *
     * @param UrlCheckResult $result
     */
    public static function ttlFor(true|array $result, int $successTtl, int $failureTtl): int
    {
        return true === $result ? $successTtl : min($successTtl, $failureTtl);
    }

    /**
     * Clear an entry from the cache — what `pw:page-scan --recheck` asks for.
     */
    public function clearCacheFor(string $url): void
    {
        $this->externalUrlCache->delete(self::cacheKey($url));
    }

    /**
     * Shared with LinkedDocsScanner, which fills the same pool on its synchronous path.
     */
    public static function cacheKey(string $url): string
    {
        return self::CACHE_PREFIX.hash('xxh3', $url);
    }
}
