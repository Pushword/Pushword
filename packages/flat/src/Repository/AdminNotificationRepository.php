<?php

declare(strict_types=1);

namespace Pushword\Flat\Repository;

use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pushword\Flat\Entity\AdminNotification;

/**
 * @extends ServiceEntityRepository<AdminNotification>
 */
final class AdminNotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminNotification::class);
    }

    /**
     * Find all unread notifications, optionally filtered by host.
     *
     * @return AdminNotification[]
     */
    public function findUnread(?string $host = null): array
    {
        $qb = $this->createQueryBuilder('n')
            ->andWhere('n.isRead = :isRead')
            ->setParameter('isRead', false)
            ->orderBy('n.createdAt', 'DESC');

        if (null !== $host) {
            $qb->andWhere('n.host = :host OR n.host IS NULL')
               ->setParameter('host', $host);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Count unread notifications, optionally filtered by host.
     */
    public function countUnread(?string $host = null): int
    {
        $qb = $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.isRead = :isRead')
            ->setParameter('isRead', false);

        if (null !== $host) {
            $qb->andWhere('n.host = :host OR n.host IS NULL')
               ->setParameter('host', $host);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(int $id): void
    {
        $notification = $this->find($id);

        if (null === $notification) {
            return;
        }

        $notification->markAsRead();
        $this->getEntityManager()->flush();
    }

    /**
     * Mark all notifications as read, optionally filtered by host.
     */
    public function markAllAsRead(?string $host = null): int
    {
        $qb = $this->createQueryBuilder('n')
            ->update()
            ->set('n.isRead', ':isRead')
            ->setParameter('isRead', true)
            ->andWhere('n.isRead = :notRead')
            ->setParameter('notRead', false);

        if (null !== $host) {
            $qb->andWhere('n.host = :host OR n.host IS NULL')
               ->setParameter('host', $host);
        }

        /** @var int<0, max> $result */
        $result = $qb->getQuery()->execute();

        return $result;
    }

    /**
     * Find notifications by type.
     *
     * @return AdminNotification[]
     */
    public function findByType(string $type, ?string $host = null, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('n')
            ->andWhere('n.type = :type')
            ->setParameter('type', $type)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit);

        if (null !== $host) {
            $qb->andWhere('n.host = :host OR n.host IS NULL')
               ->setParameter('host', $host);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Delete old notifications (older than specified days).
     */
    public function deleteOlderThan(int $days = 30): int
    {
        $date = new DateTimeImmutable(sprintf('-%d days', $days));

        /** @var int<0, max> $result */
        $result = $this->createQueryBuilder('n')
            ->delete()
            ->andWhere('n.createdAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();

        return $result;
    }
}
