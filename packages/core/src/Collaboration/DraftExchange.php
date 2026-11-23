<?php

declare(strict_types=1);

namespace Pushword\Core\Collaboration;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;

final readonly class DraftExchange
{
    public function __construct(
        private string $varDir,
        private Filesystem $filesystem,
        private LockFactory $locks,
    ) {
    }

    public function sync(Request $request, string $document, string $revision, string $baseline): JsonResponse
    {
        $content = $request->getContent();
        if (\strlen($content) > 262_144) {
            return new JsonResponse(['error' => 'update_too_large'], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $data = json_decode($content, true);
        $after = \is_array($data) ? ($data['after'] ?? null) : null;
        $update = \is_array($data) ? ($data['update'] ?? null) : null;
        if (! \is_int($after) || $after < 0
            || (null !== $update && (! \is_string($update) || '' === $update || false === base64_decode($update, true)))) {
            return new JsonResponse(['error' => 'invalid_update'], Response::HTTP_BAD_REQUEST);
        }

        $expected = $request->headers->get('If-Match');
        if ((null !== $expected && $expected !== $revision) || (null === $expected && (0 !== $after || null !== $update))) {
            return new JsonResponse(['error' => 'revision_mismatch', 'revision' => $revision], Response::HTTP_CONFLICT);
        }

        $key = 'draft-'.hash('sha256', $document.':'.$revision);
        $directory = $this->varDir.'/collaboration';
        $file = $directory.'/'.$key.'.json';
        $lock = $this->locks->createLock($key);
        $lock->acquire(true);

        try {
            $serialized = is_file($file) ? $this->filesystem->readFile($file) : '[]';
            /** @var list<string> $updates */
            $updates = json_decode($serialized, true, flags: \JSON_THROW_ON_ERROR);
            if ($after > \count($updates)) {
                return new JsonResponse(['error' => 'draft_missing'], Response::HTTP_CONFLICT);
            }

            if (null !== $update && ! \in_array($update, $updates, true)) {
                if (\strlen($serialized) + \strlen($update) > 16_777_216) {
                    return new JsonResponse(['error' => 'draft_full'], Response::HTTP_INSUFFICIENT_STORAGE);
                }

                $updates[] = $update;
                $this->filesystem->dumpFile($file, json_encode($updates, \JSON_THROW_ON_ERROR));
            }

            $batch = \array_slice($updates, $after, 64);
            $cursor = $after + \count($batch);

            return new JsonResponse([
                'revision' => $revision,
                'baseline' => 0 === $after ? $baseline : null,
                'after' => $cursor,
                'more' => $cursor < \count($updates),
                'updates' => $batch,
            ], headers: ['Cache-Control' => 'no-store']);
        } finally {
            $lock->release();
        }
    }
}
