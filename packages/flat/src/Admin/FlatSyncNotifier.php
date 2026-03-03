<?php

declare(strict_types=1);

namespace Pushword\Flat\Admin;

use Pushword\Core\Site\SiteRegistry;
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
        private SiteRegistry $apps,
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
            $this->addFlashOnce($flashBag, 'info', 'flatFilesModifiedNotification');
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

        $this->addFlashOnce($flashBag, 'warning', 'flatLockWarning', [
            '%reason%' => $this->translator->trans($lockInfo['reason'], [], 'messages'),
        ]);
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

        $filenames = array_map(basename(...), $conflicts);
        $preview = implode(', ', \array_slice($filenames, 0, 3));
        if (\count($conflicts) > 3) {
            $preview .= ', …';
        }

        $this->addFlashOnce($flashBag, 'error', 'flatUnresolvedConflicts', [
            '%count%' => \count($conflicts),
            '%files%' => $preview,
        ]);
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

    /**
     * @param array<string, string|int> $parameters
     */
    private function addFlashOnce(FlashBagInterface $flashBag, string $type, string $key, array $parameters = []): void
    {
        $message = $this->translator->trans($key, $parameters, 'messages');
        if (! \in_array($message, $flashBag->peek($type), true)) {
            $flashBag->add($type, $message);
        }
    }

    private function getFlashBag(): ?FlashBagInterface
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return null;
        }

        if (! $request->hasSession()) {
            return null;
        }

        $session = $request->getSession();
        if (! $session instanceof FlashBagAwareSessionInterface) {
            return null;
        }

        return $session->getFlashBag();
    }
}
