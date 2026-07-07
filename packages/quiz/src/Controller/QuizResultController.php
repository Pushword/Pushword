<?php

namespace Pushword\Quiz\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Quiz\Entity\QuizResult;
use Pushword\Quiz\Repository\QuizResultRepository;

use function Safe\json_decode;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * Public, anonymous endpoint storing one quiz attempt and returning the
 * percentile (how the score compares to previous participants). No auth, no PII.
 */
final class QuizResultController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly QuizResultRepository $repository,
    ) {
    }

    #[Route(path: '/quiz/result', name: 'pushword_quiz_result', methods: ['POST'])]
    public function record(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
        } catch (Throwable) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        if (! \is_array($data)) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        $quizRaw = $data['quiz'] ?? null;
        $quiz = \is_string($quizRaw) ? trim($quizRaw) : '';
        if ('' === $quiz) {
            return $this->json(['error' => 'Invalid payload'], Response::HTTP_BAD_REQUEST);
        }

        $host = $request->getHost();

        // Personality test: a chosen profile key instead of a numeric score.
        $profileRaw = $data['result'] ?? null;
        $profile = \is_string($profileRaw) ? trim($profileRaw) : '';
        if ('' !== $profile) {
            if (mb_strlen($profile) > 255) {
                return $this->json(['error' => 'Invalid payload'], Response::HTTP_BAD_REQUEST);
            }

            // Compare against prior participants before inserting the current attempt.
            $share = $this->repository->shareOfSameResult($host, $quiz, $profile);

            $entity = new QuizResult();
            $entity->host = $host;
            $entity->quiz = $quiz;
            $entity->result = $profile;

            $this->entityManager->persist($entity);
            $this->entityManager->flush();

            return $this->json(['share' => $share]);
        }

        $scoreRaw = $data['score'] ?? null;
        $score = is_numeric($scoreRaw) ? (int) $scoreRaw : -1;
        if ($score < 0 || $score > 100) {
            return $this->json(['error' => 'Invalid payload'], Response::HTTP_BAD_REQUEST);
        }

        // Compare against prior participants before inserting the current attempt.
        $percentile = $this->repository->percentileBelow($host, $quiz, $score);

        $result = new QuizResult();
        $result->host = $host;
        $result->quiz = $quiz;
        $result->score = $score;

        $this->entityManager->persist($result);
        $this->entityManager->flush();

        return $this->json(['percentile' => $percentile]);
    }
}
