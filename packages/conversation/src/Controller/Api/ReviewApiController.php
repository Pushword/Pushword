<?php

namespace Pushword\Conversation\Controller\Api;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Pushword\Api\Controller\AbstractApiController;
use Pushword\Conversation\Entity\Review;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[IsGranted('ROLE_EDITOR')]
final class ReviewApiController extends AbstractApiController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/api/review', name: 'pushword_api_review_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $pagination = $this->paginationParams($request);
        $qb = $this->entityManager->getRepository(Review::class)->createQueryBuilder('r');

        if (null !== $request->query->get('host')) {
            $qb->andWhere('r.host = :host')->setParameter('host', $request->query->getString('host'));
        }

        $total = (int) (clone $qb)->select('COUNT(r.id)')->getQuery()->getSingleScalarResult();
        $qb->orderBy('r.createdAt', 'DESC')
            ->setFirstResult($pagination['offset'])
            ->setMaxResults($pagination['perPage']);

        /** @var list<Review> $rows */
        $rows = $qb->getQuery()->getResult();
        $items = array_map($this->toArray(...), $rows);

        return $this->respond($this->paginated($items, $total, $pagination['page'], $pagination['perPage']));
    }

    #[Route('/api/review', name: 'pushword_api_review_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $this->decodeJson($request);
        $review = new Review();
        $this->apply($review, $data);

        $violations = $this->validator->validate($review);
        if (\count($violations) > 0) {
            return $this->validationErrors($violations);
        }

        $this->entityManager->persist($review);
        $this->entityManager->flush();

        return $this->respond($this->toArray($review), Response::HTTP_CREATED);
    }

    #[Route('/api/review/{id}', name: 'pushword_api_review_item', requirements: ['id' => '\d+'], methods: ['GET', 'PATCH', 'DELETE'])]
    public function item(int $id, Request $request): JsonResponse
    {
        $review = $this->entityManager->getRepository(Review::class)->find($id);
        if (! $review instanceof Review) {
            return $this->notFound('Review not found');
        }

        return match ($request->getMethod()) {
            'GET' => $this->respond($this->toArray($review)),
            'PATCH' => $this->doUpdate($review, $request),
            'DELETE' => $this->doDelete($review),
            default => $this->respond(['error' => 'Method not allowed'], Response::HTTP_METHOD_NOT_ALLOWED),
        };
    }

    private function doUpdate(Review $review, Request $request): JsonResponse
    {
        $this->apply($review, $this->decodeJson($request));
        $violations = $this->validator->validate($review);
        if (\count($violations) > 0) {
            return $this->validationErrors($violations);
        }

        $this->entityManager->flush();

        return $this->respond($this->toArray($review));
    }

    private function doDelete(Review $review): JsonResponse
    {
        $this->entityManager->remove($review);
        $this->entityManager->flush();

        return $this->noContent();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function apply(Review $review, array $data): void
    {
        // Review::setTitle / setRating route into customProperties; the validation
        // Callback wipes any unregistered customProperty key. Mirror the admin form
        // by registering them as managed before validation runs.
        $review->registerManagedPropertyKey('title');
        $review->registerManagedPropertyKey('rating');

        if (\array_key_exists('content', $data) && \is_string($data['content'])) {
            $review->setContent($data['content']);
        }

        if (\array_key_exists('title', $data) && \is_string($data['title'])) {
            $review->setTitle($data['title']);
        }

        if (\array_key_exists('rating', $data) && \is_int($data['rating'])) {
            $review->setRating($data['rating']);
        }

        if (\array_key_exists('authorName', $data) && \is_string($data['authorName'])) {
            $review->setAuthorName($data['authorName']);
        }

        if (\array_key_exists('authorEmail', $data) && \is_string($data['authorEmail'])) {
            $review->setAuthorEmail($data['authorEmail']);
        }

        if (\array_key_exists('locale', $data) && \is_string($data['locale'])) {
            $review->locale = $data['locale'];
        }

        if (\array_key_exists('host', $data) && \is_string($data['host'])) {
            $review->host = $data['host'];
        }

        if (\array_key_exists('referring', $data) && \is_string($data['referring'])) {
            $review->setReferring($data['referring']);
        }

        if (\array_key_exists('publishedAt', $data) && \is_string($data['publishedAt'])) {
            try {
                $review->setPublishedAt(new DateTimeImmutable($data['publishedAt']));
            } catch (Exception) {
                // ignore unparseable
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(Review $review): array
    {
        return [
            'id' => $review->id,
            'host' => $review->host,
            'locale' => $review->locale,
            'authorName' => $review->getAuthorName(),
            'authorEmail' => $review->getAuthorEmail(),
            'title' => $review->getTitle(),
            'content' => $review->getContent(),
            'rating' => $review->getRating(),
            'referring' => $review->getReferring(),
            'translations' => $review->getTranslations(),
            'publishedAt' => $review->getPublishedAt()?->format(DateTimeInterface::ATOM),
            'createdAt' => $review->createdAt?->format(DateTimeInterface::ATOM),
            'updatedAt' => $review->updatedAt?->format(DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function describe(): array
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'host' => ['type' => 'string'],
                'title' => ['type' => 'string'],
                'content' => ['type' => 'string'],
                'rating' => ['type' => 'integer', 'nullable' => true],
                'translations' => ['type' => 'array', 'items' => ['type' => 'object']],
                'createdAt' => ['type' => 'string', 'format' => 'date-time'],
            ],
        ];

        return [
            'paths' => [
                '/api/review' => [
                    'get' => ['summary' => 'List reviews', 'responses' => ['200' => ['description' => 'OK']]],
                    'post' => ['summary' => 'Create a review', 'responses' => ['201' => ['description' => 'Created']]],
                ],
                '/api/review/{id}' => [
                    'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                    'get' => ['summary' => 'Get a review', 'responses' => ['200' => ['description' => 'OK']]],
                    'patch' => ['summary' => 'Update a review', 'responses' => ['200' => ['description' => 'OK']]],
                    'delete' => ['summary' => 'Delete a review', 'responses' => ['204' => ['description' => 'Deleted']]],
                ],
            ],
            'components' => ['schemas' => ['Review' => $schema]],
        ];
    }
}
