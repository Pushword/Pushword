<?php

declare(strict_types=1);

namespace Pushword\Core\Collaboration;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Pushword\Core\Entity\LiveEditingState;
use Pushword\Core\Entity\Page;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;

final readonly class DraftExchange
{
    private const int MAX_SIZE = 4_194_304;

    public function __construct(
        private EntityManagerInterface $entities,
        private LockFactory $locks,
        private ClockInterface $clock,
        private LiveWrite $write,
        private Filesystem $filesystem,
        private string $varDir,
        private LiveProjection $projection,
    ) {
    }

    public function page(Request $request, Page $page): JsonResponse
    {
        return $this->sync(
            $request,
            'page:'.$page->id,
            $page,
            static fn (int $versionIncrement = 0): string => (string) ($page->writeVersion + $versionIncrement),
            static fn (): string => $page->mainContent,
            static function (string $content) use ($page): array {
                $page->mainContent = $content;

                return [];
            },
        );
    }

    /**
     * The client projects the Yjs state it has read at the requested cursor.
     * A stale cursor gets the winning snapshot so the client can merge and retry.
     *
     * @param callable(int=): string                                       $revision
     * @param callable(): string                                           $baseline
     * @param callable(string): list<array{path: string, message: string}> $apply
     */
    public function sync(Request $request, string $document, object $entity, callable $revision, callable $baseline, callable $apply): JsonResponse
    {
        if (! $this->projection->isAvailable()) {
            return $this->projection->unavailable();
        }

        $body = $request->getContent();
        if (\strlen($body) > self::MAX_SIZE) {
            return new JsonResponse(['error' => 'update_too_large'], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $data = json_decode($body, true);
        $room = \is_array($data) ? ($data['room'] ?? null) : null;
        $after = \is_array($data) ? ($data['after'] ?? null) : null;
        $snapshot = \is_array($data) ? ($data['snapshot'] ?? null) : null;
        $content = \is_array($data) ? ($data['content'] ?? null) : null;
        if (! \is_int($after) || $after < 0
            || (null === $room && (0 !== $after || null !== $snapshot))
            || (null !== $room && (! \is_string($room) || 1 !== preg_match('/^[a-f0-9]{32}$/D', $room)))
            || (null !== $snapshot && (! \is_string($snapshot) || '' === $snapshot || false === base64_decode($snapshot, true)))
            || (null === $snapshot ? null !== $content : ! \is_string($content))) {
            return new JsonResponse(['error' => 'invalid_update'], Response::HTTP_BAD_REQUEST);
        }

        $lock = $this->locks->createLock('live-editing-'.$document);
        $lock->acquire(true);

        try {
            $this->entities->refresh($entity);
            $current = $revision();
            if (null !== $room && null === $snapshot && $this->isUpToDate($document, $room, $current, $after)) {
                return $this->result($room, $current, $after);
            }

            $state = $this->entities->find(LiveEditingState::class, $document);
            if ($state instanceof LiveEditingState) {
                $this->entities->refresh($state);
            }

            $expected = $request->headers->get('If-Match');
            if (null === $room) {
                if (null !== $expected && $current !== $expected) {
                    $anchor = $state?->anchors[$expected] ?? null;

                    return $state instanceof LiveEditingState && $state->revision === $current && null !== $anchor
                        ? $this->answer($state, -1, false, anchor: $anchor)
                        : $this->conflict($current);
                }

                if (! $state instanceof LiveEditingState) {
                    $state = new LiveEditingState($document, $current);
                    $this->entities->persist($state);
                } elseif ($state->revision !== $current || null === $state->anchors) {
                    // Earlier WIP sessions contain unvalidated state. Start
                    // those rooms again from the canonical entity.
                    $state->restart($current);
                }

                $this->entities->flush();

                $seed = null === $state->snapshot ? $baseline() : null;
                $legacy = null === $seed ? [] : $this->legacyUpdates($state, ! $entity instanceof Page, $seed);

                return $this->answer($state, -1, false, $seed, legacy: $legacy);
            }

            if (! $state instanceof LiveEditingState || null === $state->anchors
                || $state->room !== $room || $state->revision !== $current || $after > $state->cursor) {
                return $this->conflict($current);
            }

            if (null === $snapshot || $after !== $state->cursor) {
                return $this->answer($state, $after, false);
            }

            return $this->commit($state, $entity, $revision, $baseline, $apply, $snapshot, $content);
        } catch (OptimisticLockException|UniqueConstraintViolationException) {
            // Refresh on the next request. The losing transaction published neither its
            // document snapshot nor its content.
            return new JsonResponse(['error' => 'retry'], Response::HTTP_SERVICE_UNAVAILABLE);
        } finally {
            $lock->release();
        }
    }

    /**
     * @param callable(int=): string                                       $revision
     * @param callable(): string                                           $baseline
     * @param callable(string): list<array{path: string, message: string}> $apply
     */
    private function commit(LiveEditingState $state, object $entity, callable $revision, callable $baseline, callable $apply, string $snapshot, string $content): JsonResponse
    {
        $now = $this->clock->now()->getTimestamp();
        $checkpoint = null === $state->lastSavedAt
            || $now - $state->lastSavedAt >= 300
            || $now - ($state->groupStartedAt ?? $now) >= 1800;

        $previous = $baseline();
        $projection = $this->projection->resolve(! $entity instanceof Page, $previous, $state->snapshot, $snapshot, $content);
        if ($projection instanceof JsonResponse) {
            return $projection;
        }

        if (\strlen($projection['snapshot']) + \strlen($projection['content']) > self::MAX_SIZE) {
            return new JsonResponse(['error' => 'update_too_large'], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        try {
            $this->write->begin($entity, $checkpoint);
            $violations = $apply($projection['content']);
            if ([] !== $violations) {
                return new JsonResponse([
                    'error' => 'invalid_content',
                    'violations' => $violations,
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $changed = $previous !== $baseline();
            if ($changed) {
                $state->remember($previous);
            }

            $state->snapshot = $projection['snapshot'];
            ++$state->cursor;
            $state->revision = $revision($changed ? 1 : 0);
            if ($changed) {
                $state->lastSavedAt = $now;
                if ($checkpoint) {
                    $state->groupStartedAt = $now;
                }
            }

            // The optimistic versions make the content and the snapshot one
            // write. A single flush also lets postFlush run after the commit.
            $this->entities->flush();

            return $this->answer($state, $state->cursor, true);
        } finally {
            $this->write->end();
        }
    }

    private function isUpToDate(string $document, string $room, string $revision, int $cursor): bool
    {
        // Idle polls only need the room header. Avoid loading full states
        // and revision anchors across the wire every 350 ms.
        $state = $this->entities->getConnection()->fetchAssociative(
            'SELECT room, revision, sync_cursor FROM live_editing_state WHERE document = ? AND anchors IS NOT NULL',
            [$document],
        );

        return false !== $state && $state['room'] === $room
            && $state['revision'] === $revision && is_numeric($state['sync_cursor']) && (int) $state['sync_cursor'] === $cursor;
    }

    private function conflict(string $revision): JsonResponse
    {
        return new JsonResponse(['error' => 'revision_mismatch', 'revision' => $revision], Response::HTTP_CONFLICT);
    }

    /** @return list<string> */
    private function legacyUpdates(LiveEditingState $state, bool $carousel, string $baseline): array
    {
        $key = hash('sha256', $state->document.':'.$state->revision);
        $path = $this->varDir.'/collaboration/draft-'.$key.'.json';
        if (! is_file($path)) {
            return [];
        }

        $legacy = json_decode($this->filesystem->readFile($path), true);
        if (! \is_array($legacy) || ! array_is_list($legacy) || [] === $legacy) {
            return [];
        }

        $updates = array_values(array_filter($legacy, is_string(...)));
        if (\count($updates) !== \count($legacy)) {
            return [];
        }

        return $this->projection->resolve($carousel, $baseline, null, null, null, $updates) instanceof JsonResponse ? [] : $updates;
    }

    /**
     * @param array{baseline: ?string, snapshot: ?string}|null $anchor
     * @param list<string>                                     $legacy
     */
    private function answer(LiveEditingState $state, int $after, bool $accepted, ?string $baseline = null, ?array $anchor = null, array $legacy = []): JsonResponse
    {
        return $this->result($state->room, $state->revision, $state->cursor, $after < $state->cursor ? $state->snapshot : null, $accepted, $baseline, $anchor, $legacy);
    }

    /**
     * @param array{baseline: ?string, snapshot: ?string}|null $anchor
     * @param list<string>                                     $legacy
     */
    private function result(string $room, string $revision, int $cursor, ?string $snapshot = null, bool $accepted = false, ?string $baseline = null, ?array $anchor = null, array $legacy = []): JsonResponse
    {
        return new JsonResponse([
            'room' => $room,
            'revision' => $revision,
            'baseline' => $baseline,
            'after' => $cursor,
            'snapshot' => $snapshot,
            'accepted' => $accepted,
            'anchor' => $anchor,
            'legacyUpdates' => $legacy,
        ], headers: ['Cache-Control' => 'no-store']);
    }
}
