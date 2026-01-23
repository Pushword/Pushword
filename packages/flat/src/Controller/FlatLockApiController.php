<?php

declare(strict_types=1);

namespace Pushword\Flat\Controller;

use Pushword\Flat\Service\FlatApiTokenValidator;
use Pushword\Flat\Service\FlatLockManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API endpoints for webhook-based lock/unlock operations.
 *
 * Usage:
 *   curl -X POST https://example.com/api/flat/lock \
 *     -H "Authorization: Bearer {api_token}" \
 *     -H "Content-Type: application/json" \
 *     -d '{"host": "example.com", "reason": "Bulk update", "ttl": 7200}'
 */
#[Route(name: 'pushword_flat_api_')]
final class FlatLockApiController extends AbstractController
{
    public function __construct(
        private readonly FlatLockManager $lockManager,
        private readonly FlatApiTokenValidator $tokenValidator,
    ) {
    }

    /**
     * Acquire a webhook lock for the given host.
     */
    #[Route('/api/flat/lock', name: 'pushword_flat_api_lock', methods: ['POST'])]
    public function lock(Request $request): JsonResponse
    {
        $user = $this->tokenValidator->validateRequest($request);

        if (null === $user) {
            return $this->unauthorizedResponse();
        }

        $data = $this->decodeJsonBody($request);
        $host = $this->getString($data, 'host');
        $reason = $this->getString($data, 'reason') ?? 'Webhook lock';
        $ttl = $this->getInt($data, 'ttl');

        $acquired = $this->lockManager->acquireWebhookLock($host, $reason, $ttl, $user->email);

        if (! $acquired) {
            $existingLock = $this->lockManager->getLockInfo($host);

            return new JsonResponse([
                'success' => false,
                'message' => 'Lock already held',
                'lockInfo' => $existingLock,
            ], Response::HTTP_CONFLICT);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Lock acquired',
            'lockInfo' => $this->lockManager->getLockInfo($host),
        ]);
    }

    /**
     * Release the webhook lock for the given host.
     */
    #[Route('/api/flat/unlock', name: 'pushword_flat_api_unlock', methods: ['POST'])]
    public function unlock(Request $request): JsonResponse
    {
        $user = $this->tokenValidator->validateRequest($request);

        if (null === $user) {
            return $this->unauthorizedResponse();
        }

        $data = $this->decodeJsonBody($request);
        $host = $this->getString($data, 'host');

        $lockInfo = $this->lockManager->getLockInfo($host);

        // Only allow unlocking webhook locks
        if (null !== $lockInfo && FlatLockManager::LOCK_TYPE_WEBHOOK !== $lockInfo['lockedBy']) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Cannot unlock non-webhook lock via API',
                'lockInfo' => $lockInfo,
            ], Response::HTTP_FORBIDDEN);
        }

        $this->lockManager->releaseLock($host);

        return new JsonResponse([
            'success' => true,
            'message' => 'Lock released',
        ]);
    }

    /**
     * Get the current lock status for the given host.
     */
    #[Route('/api/flat/status', name: 'pushword_flat_api_status', methods: ['GET'])]
    public function status(Request $request): JsonResponse
    {
        $user = $this->tokenValidator->validateRequest($request);

        if (null === $user) {
            return $this->unauthorizedResponse();
        }

        $host = $request->query->get('host');

        $isLocked = $this->lockManager->isLocked($host);
        $lockInfo = $this->lockManager->getLockInfo($host);
        $remainingTime = $this->lockManager->getRemainingTime($host);

        return new JsonResponse([
            'locked' => $isLocked,
            'isWebhookLock' => $this->lockManager->isWebhookLocked($host),
            'remainingSeconds' => $remainingTime,
            'lockInfo' => $lockInfo,
        ]);
    }

    private function unauthorizedResponse(): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'message' => 'Invalid or missing API token',
        ], Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonBody(Request $request): array
    {
        $content = $request->getContent();

        if ('' === $content) {
            return [];
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($content, true);

        return \is_array($data) ? $data : [];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function getString(array $data, string $key, ?string $default = null): ?string
    {
        $value = $data[$key] ?? $default;

        return \is_string($value) ? $value : $default;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function getInt(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        if (\is_int($value)) {
            return $value;
        }

        if (\is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return null;
    }
}
