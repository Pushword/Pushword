<?php

namespace Pushword\Core\Repository;

use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pushword\Core\Entity\LoginToken;
use Pushword\Core\Entity\User;

/**
 * @extends ServiceEntityRepository<LoginToken>
 */
class LoginTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoginToken::class);
    }

    public function findValidToken(User $user, string $tokenHash, string $type): ?LoginToken
    {
        $result = $this->createQueryBuilder('t')
            ->where('t.user = :user')
            ->andWhere('t.tokenHash = :hash')
            ->andWhere('t.type = :type')
            ->andWhere('t.used = false')
            ->andWhere('t.expiresAt > :now')
            ->setParameter('user', $user)
            ->setParameter('hash', $tokenHash)
            ->setParameter('type', $type)
            ->setParameter('now', new DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();

        return $result instanceof LoginToken ? $result : null;
    }

    public function deleteExpiredTokens(): int
    {
        return $this->createQueryBuilder('t')
            ->delete()
            ->where('t.expiresAt < :now')
            ->orWhere('t.used = true')
            ->setParameter('now', new DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    public function invalidateUserTokens(User $user, string $type): void
    {
        $this->createQueryBuilder('t')
            ->update()
            ->set('t.used', 'true')
            ->where('t.user = :user')
            ->andWhere('t.type = :type')
            ->setParameter('user', $user)
            ->setParameter('type', $type)
            ->getQuery()
            ->execute();
    }
}
