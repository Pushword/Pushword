<?php

namespace Pushword\Newsletter\Repository;

use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pushword\Newsletter\Entity\Automation;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Entity\Enrollment;
use Pushword\Newsletter\Enum\EnrollmentStatus;

/**
 * @extends ServiceEntityRepository<Enrollment>
 */
class EnrollmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Enrollment::class);
    }

    /** @return list<Enrollment> */
    public function findDue(DateTimeImmutable $now, int $limit): array
    {
        /** @var list<Enrollment> $enrollments */
        $enrollments = $this->createQueryBuilder('e')
            ->andWhere('e.status = :active')
            ->andWhere('e.nextRunAt <= :now')
            ->setParameter('active', EnrollmentStatus::Active->value)
            ->setParameter('now', $now)
            ->orderBy('e.nextRunAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $enrollments;
    }

    /**
     * How far this automation's enrollments got. Every status is present, so a
     * reader never has to tell "none yet" from "key missing".
     *
     * @return array<string, int>
     */
    public function countByStatus(Automation $automation): array
    {
        $counts = array_fill_keys(array_column(EnrollmentStatus::cases(), 'value'), 0);

        $rows = $this->createQueryBuilder('e')
            ->select('e.status AS status, COUNT(e.id) AS total')
            ->andWhere('e.automation = :automation')
            ->setParameter('automation', $automation)
            ->groupBy('e.status')
            ->getQuery()
            ->getResult();

        foreach ($rows as $row) {
            $counts[$row['status']->value] = $row['total'];
        }

        return $counts;
    }

    public function findOneFor(Contact $contact, Automation $automation): ?Enrollment
    {
        return $this->findOneBy(['contact' => $contact, 'automation' => $automation]);
    }

    /** @return list<Enrollment> */
    public function findActiveFor(Contact $contact): array
    {
        /** @var list<Enrollment> $enrollments */
        $enrollments = $this->findBy([
            'contact' => $contact,
            'status' => EnrollmentStatus::Active,
        ]);

        return $enrollments;
    }
}
