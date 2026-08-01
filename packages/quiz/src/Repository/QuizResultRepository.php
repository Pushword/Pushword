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

    /**
     * Participation per (quiz, host): how many attempts were made, the average
     * score of the knowledge ones, and how the personality ones split between
     * profiles. One grouped query — the two kinds live in the same table, told
     * apart by `result`.
     *
     * An empty $host or $quiz does not filter on it.
     *
     * @return list<array{quiz: string, host: string, attempts: int, knowledgeAttempts: int, averageScore: float|null, profiles: array<string, int>}>
     */
    public function statsBy(string $host = '', string $quiz = ''): array
    {
        $qb = $this->createQueryBuilder('r')
            ->select('r.quiz AS quiz', 'r.host AS host', 'r.result AS result', 'COUNT(r.id) AS attempts', 'AVG(r.score) AS averageScore')
            ->groupBy('r.quiz')->addGroupBy('r.host')->addGroupBy('r.result')
            ->orderBy('r.quiz', 'ASC')->addOrderBy('r.host', 'ASC')->addOrderBy('r.result', 'ASC');

        if ('' !== $host) {
            $qb->andWhere('r.host = :host')->setParameter('host', $host);
        }

        if ('' !== $quiz) {
            $qb->andWhere('r.quiz = :quiz')->setParameter('quiz', $quiz);
        }

        /** @var array<string, array{quiz: string, host: string, attempts: int, knowledgeAttempts: int, averageScore: float|null, profiles: array<string, int>}> $stats */
        $stats = [];

        foreach ($qb->getQuery()->getResult() as $row) {
            // NUL cannot appear in either value, so it separates them without
            // a quiz named after a host ever landing in the same bucket.
            $key = $row['quiz']."\0".$row['host'];
            $stats[$key] ??= [
                'quiz' => $row['quiz'],
                'host' => $row['host'],
                'attempts' => 0,
                'knowledgeAttempts' => 0,
                'averageScore' => null,
                'profiles' => [],
            ];

            $attempts = $row['attempts'];
            $stats[$key]['attempts'] += $attempts;

            if (null === $row['result']) {
                $stats[$key]['knowledgeAttempts'] = $attempts;
                $stats[$key]['averageScore'] = round($row['averageScore'], 1);

                continue;
            }

            $stats[$key]['profiles'][$row['result']] = $attempts;
        }

        return array_values($stats);
    }

    /**
     * The uuids already stored for a host, read as a single column. The flat
     * import only needs to know which rows it can skip, and hydrating every
     * result to reach one field is what made a large table costly to sync.
     *
     * @return list<string>
     */
    public function uuidsByHost(string $host): array
    {
        /** @var list<string> */
        return $this->createQueryBuilder('r')
            ->select('r.uuid')
            ->where('r.host = :host')->setParameter('host', $host)
            ->andWhere('r.uuid IS NOT NULL')
            ->getQuery()
            ->getSingleColumnResult();
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
