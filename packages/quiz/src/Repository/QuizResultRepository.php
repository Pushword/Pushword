<?php

namespace Pushword\Quiz\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pushword\Quiz\Entity\QuizResult;

/**
 * @extends ServiceEntityRepository<QuizResult>
 */
class QuizResultRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuizResult::class);
    }

    /**
     * Share of prior attempts on this quiz/host that scored strictly below $score,
     * as a 0-100 integer. Returns 0 when there is no prior attempt.
     */
    public function percentileBelow(string $host, string $quiz, int $score): int
    {
        $total = $this->countFor($host, $quiz, null);
        if (0 === $total) {
            return 0;
        }

        return (int) round($this->countFor($host, $quiz, $score) / $total * 100);
    }

    private function countFor(string $host, string $quiz, ?int $below): int
    {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.host = :host')->setParameter('host', $host)
            ->andWhere('r.quiz = :quiz')->setParameter('quiz', $quiz);

        if (null !== $below) {
            $qb->andWhere('r.score < :score')->setParameter('score', $below);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
