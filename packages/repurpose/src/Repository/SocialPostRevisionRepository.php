<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Repository;

use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pushword\Repurpose\Entity\SocialPost;
use Pushword\Repurpose\Entity\SocialPostRevision;

/** @extends ServiceEntityRepository<SocialPostRevision> */
final class SocialPostRevisionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SocialPostRevision::class);
    }

    /** @return array<int, DateTimeImmutable> */
    public function datesFor(SocialPost $post): array
    {
        /** @var list<array{version: int, savedAt: DateTimeImmutable}> $rows */
        $rows = $this->createQueryBuilder('revision')
            ->select('revision.version', 'revision.savedAt')
            ->where('revision.post = :post AND revision.version < :version')
            ->setParameter('post', $post)
            ->setParameter('version', $post->version)
            ->orderBy('revision.version', 'DESC')
            ->getQuery()->getArrayResult();

        return array_column($rows, 'savedAt', 'version');
    }

    public function findFor(SocialPost $post, int $version): ?SocialPostRevision
    {
        return $version < $post->version ? $this->findOneBy(['post' => $post, 'version' => $version]) : null;
    }
}
