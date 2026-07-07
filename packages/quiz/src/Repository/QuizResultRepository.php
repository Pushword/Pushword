<?php

namespace Pushword\Quiz\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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
     * Share of prior knowledge-quiz attempts on this quiz/host that scored strictly
     * below $score, as a 0-100 integer. Returns 0 when there is no prior attempt.
     * Personality rows (result IS NOT NULL) are ignored, so a page hosting both a
     * quiz and a personality test under one slug keeps the two tallies separate.
     */
    public function percentileBelow(string $host, string $quiz, int $score): int
    {
        $total = $this->countScores($host, $quiz, null);
        if (0 === $total) {
            return 0;
        }

        return (int) round($this->countScores($host, $quiz, $score) / $total * 100);
    }

    /**
     * Share of prior personality attempts on this quiz/host that landed on the same
     * profile, as a 0-100 integer. Returns 0 when there is no prior attempt.
     */
    public function shareOfSameResult(string $host, string $quiz, string $result): int
    {
        $total = (int) $this->baseCount($host, $quiz)
            ->andWhere('r.result IS NOT NULL')
            ->getQuery()->getSingleScalarResult();
        if (0 === $total) {
            return 0;
        }

        $same = (int) $this->baseCount($host, $quiz)
            ->andWhere('r.result = :result')->setParameter('result', $result)
            ->getQuery()->getSingleScalarResult();

        return (int) round($same / $total * 100);
    }

    /** Count knowledge-quiz rows (result IS NULL), optionally those scoring below $below. */
    private function countScores(string $host, string $quiz, ?int $below): int
    {
        $qb = $this->baseCount($host, $quiz)->andWhere('r.result IS NULL');

        if (null !== $below) {
            $qb->andWhere('r.score < :score')->setParameter('score', $below);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function baseCount(string $host, string $quiz): QueryBuilder
    {
        return $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.host = :host')->setParameter('host', $host)
            ->andWhere('r.quiz = :quiz')->setParameter('quiz', $quiz);
    }
}
