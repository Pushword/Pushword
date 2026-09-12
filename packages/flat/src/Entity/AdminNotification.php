<?php

declare(strict_types=1);

namespace Pushword\Flat\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Pushword\Flat\Repository\AdminNotificationRepository;

/**
 * Persistent admin notifications for sync events, conflicts, and errors.
 */
#[ORM\Entity(repositoryClass: AdminNotificationRepository::class)]
#[ORM\Table(name: 'admin_notification')]
#[ORM\Index(name: 'idx_type', columns: ['type'])]
#[ORM\Index(name: 'idx_is_read', columns: ['is_read'])]
#[ORM\Index(name: 'idx_created_at', columns: ['created_at'])]
#[ORM\HasLifecycleCallbacks]
class AdminNotification
{
    public const string TYPE_CONFLICT = 'conflict';

    public const string TYPE_SYNC_ERROR = 'sync_error';

    public const string TYPE_LOCK_INFO = 'lock_info';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    public ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    public string $type = '';

    #[ORM\Column(type: Types::TEXT)]
    public string $message = '';

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $host = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    public bool $isRead = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public DateTimeImmutable $createdAt;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    public array $metadata = [];

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function markAsRead(): self
    {
        $this->isRead = true;

        return $this;
    }

    public function isConflict(): bool
    {
        return self::TYPE_CONFLICT === $this->type;
    }

    public function isSyncError(): bool
    {
        return self::TYPE_SYNC_ERROR === $this->type;
    }

    public function isLockInfo(): bool
    {
        return self::TYPE_LOCK_INFO === $this->type;
    }
}
