<?php

declare(strict_types=1);

namespace Pushword\Core\Service;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Pushword\Core\Component\EntityFilter\ValueObject\PreparedSplitContent;
use Pushword\Core\Component\EntityFilter\ValueObject\SplitContent;
use Pushword\Core\Entity\Page;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;

/** One native request for all uncached documents; PHP handles declined inputs. */
final class ContentSplitter implements ResetInterface
{
    /** Bump when native analysis or document eligibility changes. */
    private const int CACHE_VERSION = 2;

    private readonly NativeWorker $worker;

    private bool $failed = false;

    public function __construct(
        #[Autowire(param: 'pw.native_content_analyzer')]
        private readonly ?string $binary = null,
        #[Autowire(param: 'pw.native_content_analyzer_timeout')]
        float $timeout = 5.0,
        #[Autowire(service: 'cache.pushword_markdown')]
        private readonly ?CacheItemPoolInterface $cache = null,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
        $this->worker = new NativeWorker($binary, $timeout);
    }

    public function split(string $html, Page $page): SplitContent
    {
        return $this->splitMany([['html' => $html, 'page' => $page]])[0];
    }

    /**
     * @param list<array{html: string, page: Page}> $documents
     *
     * @return list<SplitContent>
     */
    public function splitMany(array $documents): array
    {
        $prepared = [];
        if ([] !== $documents && null !== $this->binary && '' !== $this->binary && ! $this->failed) {
            $pending = [];
            $keys = [];
            foreach ($documents as $index => $document) {
                $page = $document['page'];
                $toc = null !== $page->getCustomProperty('toc') || null !== $page->getCustomProperty('tocTitle');
                $key = 'pw_split.'.hash('xxh3', self::CACHE_VERSION.'|'.(int) $toc.'|'.$document['html']);

                try {
                    $item = $this->cache?->getItem($key);
                    if (null !== $item && $item->isHit()) {
                        $value = $item->get();
                        if (null === $value || $value instanceof PreparedSplitContent) {
                            $prepared[$index] = $value;

                            continue;
                        }
                    }
                } catch (Throwable) {
                    // Cache availability does not affect content rendering.
                }

                $pending[$index] = ['html' => $document['html'], 'toc' => $toc];
                $keys[$index] = $key;
            }

            if ([] !== $pending) {
                try {
                    $response = $this->worker->request('split_content', array_values($pending));
                    $results = array_map(static fn (mixed $data): ?PreparedSplitContent => null === $data ? null : new PreparedSplitContent($data), $response);
                    foreach (array_keys($pending) as $offset => $index) {
                        $prepared[$index] = $results[$offset];

                        try {
                            $item = $this->cache?->getItem($keys[$index]);
                            if (null !== $item) {
                                $item->set($results[$offset]);
                                $this->cache->save($item);
                            }
                        } catch (Throwable) {
                            // The computed result remains usable without a cache write.
                        }
                    }
                } catch (Throwable $error) {
                    $this->reset();
                    $this->failed = true;
                    $prepared = [];
                    $this->logger->warning('Native content analysis failed; using PHP until the service is reset.', ['exception' => $error]);
                }
            }
        }

        $results = [];
        foreach ($documents as $index => $document) {
            $results[] = new SplitContent($document['html'], $document['page'], $this->cache, $prepared[$index] ?? null);
        }

        return $results;
    }

    public function reset(): void
    {
        $this->worker->reset();
        $this->failed = false;
    }
}
