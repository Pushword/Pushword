<?php

declare(strict_types=1);

namespace Pushword\Flat\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Pushword\Core\Service\Email\EmailEnvelope;
use Pushword\Core\Service\Email\NotificationEmailSender;
use Pushword\Flat\Entity\AdminNotification;
use Pushword\Flat\Repository\AdminNotificationRepository;

/**
 * Service for managing admin notifications and sending email alerts.
 */
final readonly class AdminNotificationService
{
    private EmailEnvelope $envelope;

    /**
     * @param string[] $emailRecipients
     */
    public function __construct(
        private EntityManagerInterface $em,
        private AdminNotificationRepository $repository,
        private NotificationEmailSender $emailSender,
        private ?LoggerInterface $logger,
        array $emailRecipients = [],
        ?string $emailFrom = null,
    ) {
        // Build envelope from flat-specific config (passed via DI)
        $this->envelope = new EmailEnvelope(
            $emailFrom ?? '',
            $emailRecipients,
        );
    }

    /**
     * Create a notification and optionally send an email.
     *
     * @param array<string, mixed> $metadata
     */
    public function createNotification(
        string $type,
        string $message,
        ?string $host = null,
        array $metadata = [],
        bool $sendEmail = false,
    ): AdminNotification {
        $notification = new AdminNotification();
        $notification->type = $type;
        $notification->message = $message;
        $notification->host = $host;
        $notification->metadata = $metadata;

        $this->em->persist($notification);
        $this->em->flush();

        if ($sendEmail) {
            $this->sendEmailNotification($notification);
        }

        return $notification;
    }

    /**
     * Create a conflict notification with email alert.
     *
     * @param array<string, mixed> $conflictData
     */
    public function notifyConflict(array $conflictData, ?string $host = null): AdminNotification
    {
        $entityType = \is_string($conflictData['entityType'] ?? null) ? $conflictData['entityType'] : 'unknown';
        $entityIdRaw = $conflictData['entityId'] ?? null;
        $entityId = \is_string($entityIdRaw) || \is_int($entityIdRaw) ? (string) $entityIdRaw : 'unknown';
        $winner = \is_string($conflictData['winner'] ?? null) ? $conflictData['winner'] : 'unknown';
        $backupFile = \is_string($conflictData['backupFile'] ?? null) ? $conflictData['backupFile'] : null;

        $message = \sprintf(
            'Conflict detected on %s #%s. Winner: %s.%s',
            $entityType,
            $entityId,
            $winner,
            null !== $backupFile ? ' Backup: '.basename($backupFile) : '',
        );

        return $this->createNotification(
            AdminNotification::TYPE_CONFLICT,
            $message,
            $host,
            $conflictData,
            sendEmail: true,
        );
    }

    /**
     * Create a sync error notification with email alert.
     */
    public function notifySyncError(string $error, ?string $host = null): AdminNotification
    {
        return $this->createNotification(
            AdminNotification::TYPE_SYNC_ERROR,
            $error,
            $host,
            ['error' => $error],
            sendEmail: true,
        );
    }

    /**
     * Create a lock info notification (no email).
     */
    public function notifyLockChange(string $action, ?string $host = null, ?string $user = null): AdminNotification
    {
        $message = \sprintf(
            'Lock %s%s%s',
            $action,
            null !== $host && '' !== $host ? ' for '.$host : '',
            null !== $user && '' !== $user ? ' by '.$user : '',
        );

        return $this->createNotification(
            AdminNotification::TYPE_LOCK_INFO,
            $message,
            $host,
            ['action' => $action, 'user' => $user],
            sendEmail: false,
        );
    }

    /**
     * Get count of unread notifications.
     */
    public function countUnread(?string $host = null): int
    {
        return $this->repository->countUnread($host);
    }

    /**
     * Get unread notifications.
     *
     * @return AdminNotification[]
     */
    public function getUnread(?string $host = null): array
    {
        return $this->repository->findUnread($host);
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(int $id): void
    {
        $this->repository->markAsRead($id);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(?string $host = null): int
    {
        return $this->repository->markAllAsRead($host);
    }

    private function sendEmailNotification(AdminNotification $notification): void
    {
        if (! $this->emailSender->canSend($this->envelope)) {
            $this->logger?->debug('[AdminNotificationService] Email not configured, skipping');

            return;
        }

        $subject = \sprintf('[Pushword] %s Alert', ucfirst($notification->type));

        $sent = $this->emailSender->sendWithRenderedHtml(
            $this->envelope,
            $subject,
            '@pwFlat/email/admin_notification.html.twig',
            ['notification' => $notification],
            $this->renderFallbackBody($notification),
        );

        if ($sent) {
            $this->logger?->info('[AdminNotificationService] Email notification sent', [
                'type' => $notification->type,
            ]);
        }
    }

    private function renderFallbackBody(AdminNotification $notification): string
    {
        return \sprintf(
            '<html><body><h2>%s Alert</h2><p>%s</p><p><small>Host: %s | Date: %s</small></p></body></html>',
            ucfirst($notification->type),
            htmlspecialchars($notification->message),
            $notification->host ?? 'N/A',
            $notification->createdAt->format('Y-m-d H:i:s'),
        );
    }
}
