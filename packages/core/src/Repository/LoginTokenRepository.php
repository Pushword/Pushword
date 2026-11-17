<?php

declare(strict_types=1);

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

    public function consumeLoginLink(LoginToken $token): bool
    {
        $updated = $this->createQueryBuilder('t')->update()
            ->set('t.used', 'true')
            ->where('t.id = :id AND t.type = :type AND t.used = false AND t.expiresAt > :now')
            ->getQuery()
            ->setParameters(['id' => $token->id, 'type' => LoginToken::TYPE_LOGIN, 'now' => new DateTimeImmutable()])
            ->execute();
        $this->getEntityManager()->refresh($token);

        return 1 === $updated;
    }

    public function issueLoginCode(LoginToken $token, string $codeHash): bool
    {
        $now = new DateTimeImmutable();
        $updated = $this->createQueryBuilder('t')->update()
            ->set('t.loginCodeHash', ':hash')
            ->set('t.loginCodeExpiresAt', ':expires')
            ->set('t.loginCodeAttempts', '0')
            ->where('t.id = :id AND t.type = :type')
            ->andWhere('t.loginCodeExpiresAt IS NULL OR t.loginCodeExpiresAt <= :resendBefore')
            ->getQuery()
            ->setParameters([
                'id' => $token->id,
                'type' => LoginToken::TYPE_LOGIN,
                'hash' => $codeHash,
                'expires' => $now->modify('+'.LoginToken::CODE_TTL_SECONDS.' seconds'),
                'resendBefore' => $now->modify('+'.(LoginToken::CODE_TTL_SECONDS - LoginToken::CODE_RESEND_SECONDS).' seconds'),
            ])->execute();
        $this->getEntityManager()->refresh($token);

        return 1 === $updated;
    }

    public function discardLoginCode(LoginToken $token, string $codeHash): void
    {
        $this->createQueryBuilder('t')->update()
            ->set('t.loginCodeHash', 'NULL')
            ->set('t.loginCodeExpiresAt', 'NULL')
            ->where('t.id = :id AND t.loginCodeHash = :hash')
            ->getQuery()
            ->setParameters(['id' => $token->id, 'hash' => $codeHash])
            ->execute();
        $this->getEntityManager()->refresh($token);
    }

    public function consumeLoginCode(LoginToken $token, string $code): bool
    {
        $codeHash = $token->loginCodeHash;
        if (null === $codeHash) {
            return false;
        }

        // Reserve an attempt in the database before checking the hash, across browsers.
        $reserved = $this->createQueryBuilder('t')->update()
            ->set('t.loginCodeAttempts', 't.loginCodeAttempts + 1')
            ->where('t.id = :id AND t.type = :type AND t.loginCodeHash = :hash')
            ->andWhere('t.loginCodeExpiresAt > :now AND t.loginCodeAttempts < :max')
            ->getQuery()
            ->setParameters([
                'id' => $token->id,
                'type' => LoginToken::TYPE_LOGIN,
                'hash' => $codeHash,
                'now' => new DateTimeImmutable(),
                'max' => LoginToken::CODE_MAX_ATTEMPTS,
            ])->execute();
        $this->getEntityManager()->refresh($token);

        if (1 !== $reserved || 1 !== preg_match('/^[0-9]{8}$/D', $code) || ! password_verify($code, $codeHash)) {
            return false;
        }

        $updated = $this->createQueryBuilder('t')->update()
            ->set('t.used', 'true')
            ->set('t.loginCodeHash', 'NULL')
            ->set('t.sessionVersion', 't.sessionVersion + 1')
            ->where('t.id = :id AND t.loginCodeHash = :hash AND t.loginCodeExpiresAt > :now')
            ->getQuery()
            ->setParameters(['id' => $token->id, 'hash' => $codeHash, 'now' => new DateTimeImmutable()])
            ->execute();
        $this->getEntityManager()->refresh($token);

        return 1 === $updated;
    }

    public function isSessionCurrent(int $tokenId, int $version, User $user): bool
    {
        // Session lifetime is independent of the email link's expiry.
        return 1 === (int) $this->createQueryBuilder('t')->select('COUNT(t.id)')
            ->where('t.id = :id AND t.sessionVersion = :version AND t.user = :user')
            ->getQuery()
            ->setParameters(['id' => $tokenId, 'version' => $version, 'user' => $user])
            ->getSingleScalarResult();
    }
}
