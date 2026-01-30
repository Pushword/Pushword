<?php

namespace Pushword\PageScanner\Scanner;

use CurlHandle;
use RuntimeException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ParallelUrlChecker
{
    private const string DEFAULT_USER_AGENT = 'Mozilla/5.0 (Linux; Android 11; Pixel 5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.91 Mobile Safari/537.36';

    /** @var array<string, true|string> */
    private array $results = [];

    public function __construct(
        private readonly CacheInterface $externalUrlCache,
        private readonly TranslatorInterface $translator,
        private readonly int $externalUrlCacheTtl = 86400,
        private readonly int $parallelBatchSize = 50,
        private readonly int $urlCheckTimeoutMs = 10000,
    ) {
    }

    /**
     * Check multiple URLs in parallel using curl_multi.
     *
     * @param string[] $urls
     *
     * @return array<string, true|string> URL => true or error message
     */
    public function checkUrls(array $urls): array
    {
        $urls = array_unique($urls);
        $this->results = [];

        $uncachedUrls = [];
        foreach ($urls as $url) {
            $cacheKey = 'url_'.hash('xxh3', $url);

            /** @var true|string|null $cached */
            $cached = $this->externalUrlCache->get($cacheKey, static fn (): null => null);

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
        $multiHandle = curl_multi_init();
        /** @var array<string, CurlHandle> $handles */
        $handles = [];

        foreach ($urls as $url) {
            $ch = $this->createCurlHandle($url);
            curl_multi_add_handle($multiHandle, $ch);
            $handles[$url] = $ch;
        }

        $running = null;
        do {
            curl_multi_exec($multiHandle, $running);
            curl_multi_select($multiHandle);
        } while ($running > 0);

        foreach ($handles as $url => $ch) {
            $httpCode = curl_getinfo($ch, \CURLINFO_HTTP_CODE);
            $error = curl_errno($ch);

            $result = $this->interpretResult($httpCode, $error, curl_error($ch));
            $this->results[$url] = $result;

            $this->cacheResult($url, $result);

            curl_multi_remove_handle($multiHandle, $ch);
            unset($ch);
        }

        curl_multi_close($multiHandle);
    }

    private function createCurlHandle(string $url): CurlHandle
    {
        $ch = curl_init($url);
        if (false === $ch) {
            throw new RuntimeException('Failed to initialize curl handle');
        }

        curl_setopt_array($ch, [
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_HEADER => true,
            \CURLOPT_NOBODY => true,
            \CURLOPT_FOLLOWLOCATION => false,
            \CURLOPT_MAXREDIRS => 0,
            \CURLOPT_CONNECTTIMEOUT_MS => 5000,
            \CURLOPT_TIMEOUT_MS => $this->urlCheckTimeoutMs,
            \CURLOPT_SSL_VERIFYHOST => 0,
            \CURLOPT_SSL_VERIFYPEER => false,
            \CURLOPT_USERAGENT => self::DEFAULT_USER_AGENT,
            \CURLOPT_ENCODING => 'gzip, deflate',
        ]);

        return $ch;
    }

    private function interpretResult(int $httpCode, int $error, string $errorMessage): true|string
    {
        if (in_array($httpCode, [200, 403, 410], true)) {
            return true;
        }

        if ($httpCode > 0) {
            return $this->translator->trans('page_scanStatusCode').' ('.$httpCode.')';
        }

        if ($error > 0) {
            return $this->translator->trans('page_scanUnreachable', ['errorMessage' => $errorMessage]);
        }

        return true;
    }

    private function cacheResult(string $url, true|string $result): void
    {
        $cacheKey = 'url_'.hash('xxh3', $url);

        $this->externalUrlCache->get($cacheKey, function (ItemInterface $item) use ($result): true|string {
            $item->expiresAfter($this->externalUrlCacheTtl);

            return $result;
        });
    }

    /**
     * Clear an entry from the cache (useful for re-checking specific URLs).
     */
    public function clearCacheFor(string $url): void
    {
        $cacheKey = 'url_'.hash('xxh3', $url);
        $this->externalUrlCache->delete($cacheKey);
    }
}
