<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EDITOR')]
final class CollaborationPocController extends AbstractController
{
    private const string ROOM = '[a-f0-9]{16}';

    public function __construct(
        #[Autowire(service: 'cache.app')]
        private readonly CacheItemPoolInterface $cache,
        private readonly LockFactory $locks,
    ) {
    }

    #[Route('/admin/collaboration-poc', name: 'collaboration_poc_new', methods: ['GET'])]
    public function start(): RedirectResponse
    {
        return $this->redirectToRoute('collaboration_poc_editor', ['room' => bin2hex(random_bytes(8))]);
    }

    #[Route('/admin/collaboration-poc/{room}', name: 'collaboration_poc_editor', requirements: ['room' => self::ROOM], methods: ['GET'])]
    public function editor(string $room): Response
    {
        return $this->render('collaboration_poc.html.twig', ['room' => $room]);
    }

    #[Route('/admin/collaboration-poc/{room}/sync', name: 'collaboration_poc_sync', requirements: ['room' => self::ROOM], methods: ['POST'])]
    public function browserSync(Request $request, string $room): JsonResponse
    {
        if (! $this->isCsrfTokenValid('collaboration_poc_'.$room, $request->headers->get('X-Collaboration-Token'))) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        return $this->sync($request, $room);
    }

    #[Route('/api/collaboration-poc/{room}/sync', name: 'collaboration_poc_agent_sync', requirements: ['room' => self::ROOM], methods: ['POST'])]
    public function agentSync(Request $request, string $room): JsonResponse
    {
        return $this->sync($request, $room);
    }

    private function sync(Request $request, string $room): JsonResponse
    {
        $content = $request->getContent();
        if (\strlen($content) > 262_144) {
            return new JsonResponse(['error' => 'Update too large'], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $data = json_decode($content, true);
        $after = \is_array($data) ? ($data['after'] ?? null) : null;
        $update = \is_array($data) ? ($data['update'] ?? null) : null;
        if (! \is_int($after) || $after < 0
            || (null !== $update && (! \is_string($update) || '' === $update || false === base64_decode($update, true)))) {
            return new JsonResponse(['error' => 'Expected a cursor and an optional base64 update'], Response::HTTP_BAD_REQUEST);
        }

        $key = 'collaboration_poc.'.$room;
        $lock = $this->locks->createLock($key);
        $lock->acquire(true);

        try {
            $item = $this->cache->getItem($key);
            /** @var list<string> $updates */
            $updates = $item->get() ?? [];
            if ($after > \count($updates)) {
                return new JsonResponse(['error' => 'The demo room was cleared'], Response::HTTP_CONFLICT);
            }

            if (null !== $update && ! \in_array($update, $updates, true)) {
                $updates[] = $update;
                if (! $this->cache->save($item->set($updates))) {
                    return new JsonResponse(['error' => 'Could not store the update'], Response::HTTP_SERVICE_UNAVAILABLE);
                }
            }

            return new JsonResponse(['after' => \count($updates), 'updates' => \array_slice($updates, $after)], headers: ['Cache-Control' => 'no-store']);
        } finally {
            $lock->release();
        }
    }
}
