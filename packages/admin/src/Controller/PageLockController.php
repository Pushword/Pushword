<?php

declare(strict_types=1);

namespace Pushword\Admin\Controller;

use Doctrine\ORM\AbstractQuery;
use Pushword\Admin\Service\PageEditLockManager;
use Pushword\Core\Collaboration\LivePresence;
use Pushword\Core\Entity\User;
use Pushword\Core\Repository\PageRepository;
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
        private readonly PageRepository $pages,
        private readonly LivePresence $presence,
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

        $version = $this->pages->createQueryBuilder('p')
            ->select('p.writeVersion')
            ->where('p.id = :id')
            ->setParameter('id', $pageId)
            ->getQuery()
            ->getOneOrNullResult(AbstractQuery::HYDRATE_SINGLE_SCALAR);
        if (! is_numeric($version)) {
            return new JsonResponse(['error' => 'Page not found'], Response::HTTP_NOT_FOUND);
        }

        // Release any session reopened after SessionSaveListener. Authentication
        // already read it at kernel.request, so this cannot avoid an earlier wait
        // for another request's session lock. Later session access can lock it again.
        if ($request->hasSession() && $request->getSession()->isStarted()) {
            $request->getSession()->save();
        }

        $data = json_decode($request->getContent(), true);
        $tabId = \is_array($data) && \is_string($data['tabId'] ?? null) ? $data['tabId'] : null;

        $acquired = $this->lockManager->acquireOrRefresh($pageId, $user, $tabId);
        $lockInfo = $this->lockManager->getLockInfo($pageId);

        // Determine if locked by same user (different tab) or different user
        $isSameUser = null !== $lockInfo && $lockInfo['userId'] === $user->id;

        return new JsonResponse([
            'acquired' => $acquired,
            'lockInfo' => $lockInfo,
            'isOwner' => $acquired,
            'isSameUser' => $isSameUser,
            'revision' => (string) $version,
            ...$this->presence->snapshot('page:'.$pageId, $user),
        ]);
    }
}
