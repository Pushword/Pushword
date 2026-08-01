<?php

namespace Pushword\Quiz\Controller\Api;

use DateTimeInterface;
use Pushword\Api\Controller\AbstractApiController;
use Pushword\Quiz\Entity\QuizResult;
use Pushword\Quiz\Repository\QuizResultRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * What the quizzes collected, read from outside the admin: the attempts
 * themselves, and the tally the admin index can only be eyeballed for.
 *
 * Read-only. Attempts are anonymous data points written by the public endpoint
 * and round-tripped through `quiz-result.csv`; the API has nothing to add to
 * them, and deleting one here would see it recreated by the next flat import.
 */
#[IsGranted('ROLE_EDITOR')]
final class QuizResultApiController extends AbstractApiController
{
    public function __construct(private readonly QuizResultRepository $quizResultRepository)
    {
    }

    #[Route(path: '/api/quiz/result', name: 'pushword_api_quiz_result_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $pagination = $this->paginationParams($request);
        $queryBuilder = $this->quizResultRepository->createQueryBuilder('r');

        if (null !== $request->query->get('host')) {
            $queryBuilder->andWhere('r.host = :host')->setParameter('host', $request->query->getString('host'));
        }

        if (null !== $request->query->get('quiz')) {
            $queryBuilder->andWhere('r.quiz = :quiz')->setParameter('quiz', $request->query->getString('quiz'));
        }

        $total = (int) (clone $queryBuilder)->select('COUNT(r.id)')->getQuery()->getSingleScalarResult();
        $queryBuilder->orderBy('r.createdAt', 'DESC')->addOrderBy('r.id', 'DESC')
            ->setFirstResult($pagination['offset'])
            ->setMaxResults($pagination['perPage']);

        /** @var list<QuizResult> $quizResults */
        $quizResults = $queryBuilder->getQuery()->getResult();

        return $this->respond($this->paginated(array_map($this->toArray(...), $quizResults), $total, $pagination['page'], $pagination['perPage']));
    }

    /**
     * Every matching quiz, unpaginated: a site holds as many rows here as it has
     * quizzes, and a truncated tally is a misleading one.
     */
    #[Route(path: '/api/quiz/result/stats', name: 'pushword_api_quiz_result_stats', methods: ['GET'])]
    public function stats(Request $request): JsonResponse
    {
        $stats = $this->quizResultRepository->statsBy(
            $request->query->getString('host'),
            $request->query->getString('quiz'),
        );

        return $this->respond(['items' => $stats, 'total' => \count($stats)]);
    }

    /** @return array<string, mixed> */
    private function toArray(QuizResult $quizResult): array
    {
        return [
            'id' => $quizResult->id,
            'uuid' => $quizResult->uuid,
            'quiz' => $quizResult->quiz,
            'host' => $quizResult->host,
            'score' => $quizResult->score,
            'result' => $quizResult->result,
            'createdAt' => $quizResult->createdAt?->format(DateTimeInterface::ATOM),
        ];
    }

    /** @return array<string, mixed> */
    public static function describe(): array
    {
        return [
            'paths' => [
                '/api/quiz/result' => [
                    'get' => [
                        'summary' => 'List quiz attempts, newest first',
                        'description' => 'Anonymous data points: one row per completed quiz. `score` is a percentage; `result` names the profile of a personality test and is null for a knowledge quiz.',
                        'tags' => ['Quiz'],
                        'parameters' => [
                            ['name' => 'host', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'quiz', 'in' => 'query', 'description' => 'Quiz title, as declared in the page', 'schema' => ['type' => 'string']],
                        ],
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                ],
                '/api/quiz/result/stats' => [
                    'get' => [
                        'summary' => 'Participation per quiz and host',
                        'description' => 'Attempts, the average score of the knowledge ones, and the profile split of the personality ones. Unpaginated.',
                        'tags' => ['Quiz'],
                        'parameters' => [
                            ['name' => 'host', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'quiz', 'in' => 'query', 'schema' => ['type' => 'string']],
                        ],
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                ],
            ],
            'components' => [
                'schemas' => [
                    'QuizStats' => [
                        'type' => 'object',
                        'properties' => [
                            'quiz' => ['type' => 'string'],
                            'host' => ['type' => 'string'],
                            'attempts' => ['type' => 'integer', 'description' => 'Both kinds together'],
                            'knowledgeAttempts' => ['type' => 'integer'],
                            'averageScore' => ['type' => 'number', 'nullable' => true, 'description' => 'Over the knowledge attempts only; null when there is none'],
                            'profiles' => ['type' => 'object', 'description' => 'Profile key to how many personality attempts landed on it', 'additionalProperties' => ['type' => 'integer']],
                        ],
                    ],
                ],
            ],
        ];
    }
}
