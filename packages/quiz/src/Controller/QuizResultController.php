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
        $response = $this->handle($request);

        // Anonymous, no-credential stats: any origin may read the response. CORS
        // only gates the browser reading the reply (the POST itself is never
        // blocked), so a wildcard is enough and needs no per-site origin allowlist.
        $response->headers->set('Access-Control-Allow-Origin', '*');

        return $response;
    }

    private function handle(Request $request): JsonResponse
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

        $host = $this->resolveHost($request);

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

    /**
     * Scope the attempt to the site the visitor is actually on. On a statically
     * served page the request reaches the dynamic host cross-origin, so
     * getHost() would be that shared PHP host and collapse every site's stats
     * together. The Origin header carries the real site host and, being
     * browser-set, cannot be spoofed by a page script.
     */
    private function resolveHost(Request $request): string
    {
        $origin = $request->headers->get('origin');
        if (null !== $origin) {
            $originHost = parse_url($origin, \PHP_URL_HOST);
            if (\is_string($originHost) && '' !== $originHost) {
                return $originHost;
            }
        }

        return $request->getHost();
    }
}
