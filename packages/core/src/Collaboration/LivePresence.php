<?php

declare(strict_types=1);

namespace Pushword\Core\Collaboration;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;

final readonly class LivePresence
{
    private const int LIFETIME = 12;

    public function __construct(
        #[Autowire(service: 'cache.app')]
        private CacheItemPoolInterface $cache,
        private ClockInterface $clock,
        private LockFactory $locks,
    ) {
    }

    /** @param callable(): JsonResponse $exchange */
    public function sync(Request $request, string $document, callable $exchange): JsonResponse
    {
        $tab = $request->headers->get('X-Collaboration-Tab');
        if (null !== $tab && 1 !== preg_match('/\A[a-f0-9]{32}\z/', $tab)) {
            $tab = null;
        }

        if ($request->isMethod(Request::METHOD_DELETE)) {
            if (null !== $tab) {
                $this->update($document, $tab, false);
            }

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        $response = $exchange();
        if (null !== $tab && $response->isSuccessful()) {
            $this->update($document, $tab, true);
        }

        return $response;
    }

    public function isActive(string $document): bool
    {
        return [] !== $this->activeTabs($this->cache->getItem($this->key($document))->get());
    }

    private function update(string $document, string $tab, bool $present): void
    {
        $key = $this->key($document);
        $lock = $this->locks->createLock($key);
        $lock->acquire(true);

        try {
            $item = $this->cache->getItem($key);
            $active = $this->activeTabs($item->get());

            if ($present) {
                $active[$tab] = $this->clock->now()->getTimestamp() + self::LIFETIME;
            } else {
                unset($active[$tab]);
            }

            $this->cache->save($item->set($active)->expiresAfter(self::LIFETIME));
        } finally {
            $lock->release();
        }
    }

    /** @return array<array-key, int> */
    private function activeTabs(mixed $entries): array
    {
        if (! \is_array($entries)) {
            return [];
        }

        $now = $this->clock->now()->getTimestamp();

        return array_filter($entries, static fn (mixed $until): bool => \is_int($until) && $until > $now);
    }

    private function key(string $document): string
    {
        return 'live_presence.'.hash('sha256', $document);
    }
}
