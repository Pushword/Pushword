<?php

declare(strict_types=1);

namespace Pushword\Admin\Controller;

use Pushword\Admin\Service\PageEditLockManager;
use Pushword\Core\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EDITOR')]
final class PageLockController extends AbstractController
{
    public function __construct(
        private readonly PageEditLockManager $lockManager,
    ) {
    }

    /**
     * Ping endpoint to maintain lock and check status.
     * Returns current lock state and lastSavedAt timestamp.
     */
    #[Route(
        path: '/admin/page/{pageId}/lock/ping',
        name: 'pushword_page_lock_ping',
        methods: ['POST'],
    )]
    public function ping(int $pageId, Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (! $user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        $tabId = \is_array($data) && \is_string($data['tabId'] ?? null) ? $data['tabId'] : null;

        $acquired = $this->lockManager->acquireOrRefresh($pageId, $user, $tabId);
        $lockInfo = $this->lockManager->getLockInfo($pageId);

        // Determine if locked by same user (different tab) or different user
        $isSameUser = null !== $lockInfo && $lockInfo['userId'] === $user->getId();

        return new JsonResponse([
            'acquired' => $acquired,
            'lockInfo' => $lockInfo,
            'isOwner' => $acquired,
            'isSameUser' => $isSameUser,
        ]);
    }
}
