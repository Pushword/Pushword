<?php

declare(strict_types=1);

namespace Pushword\Flat\Admin;

use Pushword\Core\Component\App\AppPool;
use Pushword\Flat\Service\FlatChangeDetector;
use Pushword\Flat\Service\FlatLockManager;
use Pushword\Flat\Sync\ConflictResolver;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Notifies admin users about flat sync status, changes, and conflicts.
 */
final readonly class FlatSyncNotifier
{
    public function __construct(
        private FlatChangeDetector $changeDetector,
        private FlatLockManager $lockManager,
        private ConflictResolver $conflictResolver,
        private TranslatorInterface $translator,
        private RequestStack $requestStack,
        private AppPool $apps,
    ) {
    }

    /**
     * Notify if there are flat file changes that need to be synced.
     */
    public function notifyIfChangesDetected(?string $host = null): void
    {
        $flashBag = $this->getFlashBag();
        if (null === $flashBag) {
            return;
        }

        $resolvedHost = $host ?? $this->apps->getMainHost();
        $changes = $this->changeDetector->checkForChanges($resolvedHost);

        if ($changes['hasChanges']) {
            $flashBag->add('info', $this->translator->trans('flatFilesModifiedNotification', [], 'messages'));
        }
    }

    /**
     * Notify if there's an active lock on flat files.
     */
    public function notifyIfLocked(?string $host = null): void
    {
        $flashBag = $this->getFlashBag();
        if (null === $flashBag) {
            return;
        }

        $resolvedHost = $host ?? $this->apps->getMainHost();

        if (! $this->lockManager->isLocked($resolvedHost)) {
            return;
        }

        $lockInfo = $this->lockManager->getLockInfo($resolvedHost);
        if (null === $lockInfo) {
            return;
        }

        $flashBag->add('warning', $this->translator->trans('flatLockWarning', [
            '%reason%' => $lockInfo['reason'],
        ], 'messages'));
    }

    /**
     * Notify if there are unresolved conflicts.
     */
    public function notifyIfUnresolvedConflicts(?string $host = null): void
    {
        $flashBag = $this->getFlashBag();
        if (null === $flashBag) {
            return;
        }

        $resolvedHost = $host ?? $this->apps->getMainHost();
        $conflicts = $this->conflictResolver->findUnresolvedConflicts($resolvedHost);

        if ([] === $conflicts) {
            return;
        }

        $flashBag->add('error', $this->translator->trans('flatUnresolvedConflicts', [
            '%count%' => \count($conflicts),
        ], 'messages'));
    }

    /**
     * Run all notifications (changes, lock, conflicts).
     */
    public function notifyAll(?string $host = null): void
    {
        $this->notifyIfUnresolvedConflicts($host);
        $this->notifyIfLocked($host);
        $this->notifyIfChangesDetected($host);
    }

    /**
     * Check if there are any pending issues (changes, lock, or conflicts).
     *
     * @return array{hasChanges: bool, isLocked: bool, hasConflicts: bool, conflictCount: int}
     */
    public function getStatus(?string $host = null): array
    {
        $resolvedHost = $host ?? $this->apps->getMainHost();

        $changes = $this->changeDetector->checkForChanges($resolvedHost);
        $conflicts = $this->conflictResolver->findUnresolvedConflicts($resolvedHost);

        return [
            'hasChanges' => $changes['hasChanges'],
            'isLocked' => $this->lockManager->isLocked($resolvedHost),
            'hasConflicts' => [] !== $conflicts,
            'conflictCount' => \count($conflicts),
        ];
    }

    private function getFlashBag(): ?FlashBagInterface
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return null;
        }

        $session = $request->getSession();
        if (! $session instanceof FlashBagAwareSessionInterface) {
            return null;
        }

        return $session->getFlashBag();
    }
}
