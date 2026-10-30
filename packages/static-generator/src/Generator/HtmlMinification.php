<?php

declare(strict_types=1);

namespace Pushword\StaticGenerator\Generator;

use Composer\InstalledVersions;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Pushword\Core\Service\NativeWorker;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;

/** PHP is the default; the optional worker only receives already rendered HTML. */
final class HtmlMinification implements ResetInterface
{
    /** Bump whenever PHP minification, native fallback or SVG serialization changes output. */
    private const int CACHE_VERSION = 4;

    // Numeric references may omit their semicolon; reject digit prefixes of valid references.
    private const string PHP_SERIALIZATION_REQUIRED = '/[\x00-\x08\x0b\x0c\x0e-\x1f]|&#(?:0*(?:[0-9]|[12][0-9]|3[01])(?![0-9])|x0*(?:[0-9a-f]|1[0-9a-f])(?![0-9a-f]))/i';

    private const string PARITY_PROBE = '<!DOCTYPE html><html><body><a href="/?x=&quot;[\\]^`{|}">x</a><svg xml:space="preserve" xml:lang="fr"></svg></body></html>';

    private readonly NativeWorker $worker;

    private bool $failed = false;

    private bool $parityChecked = false;

    private string $operation = 'minify_html';

    private ?string $cacheVersion = null;

    public function __construct(
        private readonly ?string $binary = null,
        float $timeout = 5.0,
        private readonly LoggerInterface $logger = new NullLogger(),
        #[Autowire(service: 'cache.pushword_markdown')]
        private readonly ?CacheItemPoolInterface $cache = null,
    ) {
        $this->worker = new NativeWorker($binary, $timeout);
    }

    public function compress(string $html): string
    {
        return $this->compressMany([$html])[0];
    }

    /**
     * @param list<string> $documents
     *
     * @return list<string>
     */
    public function compressMany(array $documents): array
    {
        if ([] === $documents) {
            return [];
        }

        if (! $this->cache instanceof CacheItemPoolInterface) {
            return $this->compressUncached($documents);
        }

        $native = $this->usesNative();
        $results = [];
        $missing = [];
        $items = [];

        try {
            $version = $this->cacheVersion();
            if (null === $version) {
                return $this->compressUncached($documents);
            }

            foreach ($documents as $index => $html) {
                $item = $this->cache->getItem('pw_html.'.hash('xxh128', $version.'|'.$html));
                $cached = $item->isHit() ? $item->get() : null;
                if (\is_string($cached)) {
                    $results[$index] = $cached;
                } else {
                    $missing[] = $html;
                    $items[$index] = $item;
                }
            }
        } catch (Throwable) {
            return $this->compressUncached($documents);
        }

        $skipped = HtmlMinifier::$skippedOnBrokenLibxml;
        $compressed = $this->compressUncached($missing);

        // A failed native batch must use PHP for every document, including hits.
        if ($native !== $this->usesNative()) {
            return $this->compressMany($documents);
        }

        $offset = 0;
        foreach ($items as $index => $item) {
            $results[$index] = $compressed[$offset++];
            // Keep reporting libxml's lossy serialization on every affected build.
            if ($skipped !== HtmlMinifier::$skippedOnBrokenLibxml) {
                continue;
            }

            try {
                $item->set($results[$index]);
                $this->cache->save($item);
            } catch (Throwable) {
                // A failed cache write must not discard successfully minified HTML.
            }
        }

        ksort($results);

        return array_values($results);
    }

    private function usesNative(): bool
    {
        return null !== $this->binary && '' !== $this->binary && ! $this->failed;
    }

    private function cacheVersion(): ?string
    {
        if (null !== $this->cacheVersion) {
            return $this->cacheVersion;
        }

        $engine = 'php';
        if ($this->usesNative() && null !== $this->binary) {
            if (! is_file($this->binary) || ! is_readable($this->binary)) {
                return null;
            }

            $engine = 'native.'.hash_file('xxh128', $this->binary);
        }

        return $this->cacheVersion = self::CACHE_VERSION.'|'.$engine.'|'.\PHP_VERSION.'|'.\LIBXML_DOTTED_VERSION.'|'.\LIBXML_LOADED_VERSION.'|'.InstalledVersions::getVersion('symfony/dom-crawler').'|'.InstalledVersions::getVersion('masterminds/html5');
    }

    /**
     * @param list<string> $documents
     *
     * @return list<string>
     */
    private function compressUncached(array $documents): array
    {
        if ([] === $documents) {
            return [];
        }

        if ($this->usesNative()) {
            try {
                if (! $this->parityChecked) {
                    $expected = HtmlMinifier::compress(self::PARITY_PROBE);
                    $this->operation = match (true) {
                        ! str_contains($expected, 'href="') => 'minify_html',
                        str_contains($expected, 'xml:space=') => 'minify_html5_with_xml_prefixes',
                        default => 'minify_html5',
                    };
                    $result = $this->compressNative([self::PARITY_PROBE, ...$documents]);
                    if (array_shift($result) !== $expected) {
                        throw new RuntimeException('Native HTML serialization differs from PHP');
                    }

                    $this->parityChecked = true;

                    return $result;
                }

                return $this->compressNative($documents);
            } catch (Throwable $error) {
                $this->reset();
                // Avoid repeating process startup, timeouts and warnings for every page.
                $this->failed = true;
                $this->logger->warning('Native HTML minification failed; using PHP until the service is reset.', ['exception' => $error]);
            }
        }

        return array_map(HtmlMinifier::compress(...), $documents);
    }

    public function reset(): void
    {
        $this->worker->reset();
        $this->failed = false;
        $this->parityChecked = false;
        $this->operation = 'minify_html';
        $this->cacheVersion = null;
    }

    /**
     * @param list<string> $documents
     *
     * @return list<string>
     */
    private function compressNative(array $documents): array
    {
        $validated = [];
        foreach ($this->worker->request($this->operation, $documents) as $index => $html) {
            if (! \is_string($html)) {
                throw new RuntimeException('Invalid native HTML document');
            }

            // Match the installed PHP/libxml serialization of controls and their numeric references.
            $validated[] = 1 === preg_match(self::PHP_SERIALIZATION_REQUIRED, $documents[$index])
                ? HtmlMinifier::compress($documents[$index])
                : $html;
        }

        return $validated;
    }
}
