<?php

declare(strict_types=1);

namespace Pushword\Core\Collaboration;

use Psr\Cache\CacheItemPoolInterface;
use Pushword\Core\Entity\User;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;

/**
 * @phpstan-type Participant array{id: int|null, name: string, api: bool}
 * @phpstan-type PresenceEntry array{expiresAt: int, participant: Participant}
 */
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
    public function sync(Request $request, string $document, callable $exchange, ?User $user = null): JsonResponse
    {
        $tab = $request->headers->get('X-Collaboration-Tab');
        if (null !== $tab && 1 !== preg_match('/\A[a-f0-9]{32}\z/', $tab)) {
            $tab = null;
        }

        if (null !== $tab && $user instanceof User) {
            $tab = $user->id.':'.$tab;
        }

        if ($request->isMethod(Request::METHOD_DELETE)) {
            if (null !== $tab) {
                $this->update($document, $tab, false);
            }

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        $response = $exchange();
        if (null !== $tab && $response->isSuccessful()) {
            $this->update($document, $tab, true, $user);
        }

        return $response;
    }

    public function recordApi(string $document, User $user, JsonResponse $response): void
    {
        if ($response->isSuccessful()) {
            $this->update($document, 'api:'.$user->id, true, $user, api: true);
        }
    }

    public function isActive(string $document): bool
    {
        return [] !== $this->activeTabs($this->cache->getItem($this->key($document))->get());
    }

    /** @return array{collaborative: bool, participants: list<Participant>} */
    public function snapshot(string $document, User $viewer): array
    {
        $active = $this->activeTabs($this->cache->getItem($this->key($document))->get());
        $participants = [];

        foreach ($active as $entry) {
            if (\is_int($entry)) {
                continue;
            }

            $participant = $entry['participant'];
            if ($participant['api'] || $participant['id'] !== $viewer->id) {
                $participants[($participant['api'] ? 'api:' : 'browser:').$participant['id']] = $participant;
            }
        }

        return ['collaborative' => [] !== $active, 'participants' => array_values($participants)];
    }

    private function update(string $document, string $tab, bool $present, ?User $user = null, bool $api = false): void
    {
        $key = $this->key($document);
        $lock = $this->locks->createLock($key);
        $lock->acquire(true);

        try {
            $item = $this->cache->getItem($key);
            $active = $this->activeTabs($item->get());

            if ($present) {
                $expiresAt = $this->clock->now()->getTimestamp() + self::LIFETIME;
                $active[$tab] = $user instanceof User ? [
                    'expiresAt' => $expiresAt,
                    'participant' => [
                        'id' => $user->id,
                        'name' => trim($user->username ?? '') ?: explode('@', $user->email, 2)[0],
                        'api' => $api,
                    ],
                ] : $expiresAt;
            } else {
                unset($active[$tab]);
            }

            $this->cache->save($item->set($active)->expiresAfter(self::LIFETIME));
        } finally {
            $lock->release();
        }
    }

    /** @return array<string, int|PresenceEntry> */
    private function activeTabs(mixed $entries): array
    {
        if (! \is_array($entries)) {
            return [];
        }

        $now = $this->clock->now()->getTimestamp();

        /** @var array<string, int|PresenceEntry> $entries */
        return array_filter($entries, static fn (int|array $entry): bool => (\is_int($entry) ? $entry : $entry['expiresAt']) > $now);
    }

    private function key(string $document): string
    {
        return 'live_presence.'.hash('sha256', $document);
    }
}
