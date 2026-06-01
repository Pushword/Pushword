<?php

namespace Pushword\Snippet\Controller\Api;

use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Pushword\Api\Controller\AbstractApiController;
use Pushword\Snippet\Entity\Snippet;
use Pushword\Snippet\Repository\SnippetRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[IsGranted('ROLE_EDITOR')]
final class SnippetApiController extends AbstractApiController
{
    public function __construct(
        private readonly SnippetRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/api/snippet', name: 'pushword_api_snippet_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $pagination = $this->paginationParams($request);
        $qb = $this->repository->createQueryBuilder('s');

        if (null !== $request->query->get('host')) {
            $qb->andWhere('s.host = :host')->setParameter('host', $request->query->getString('host'));
        }

        if (null !== $request->query->get('q')) {
            $qb->andWhere('s.slug LIKE :q OR s.name LIKE :q OR s.content LIKE :q')
                ->setParameter('q', '%'.$request->query->getString('q').'%');
        }

        $total = (int) (clone $qb)->select('COUNT(s.id)')->getQuery()->getSingleScalarResult();
        $qb->orderBy('s.slug', 'ASC')
            ->setFirstResult($pagination['offset'])
            ->setMaxResults($pagination['perPage']);

        $items = array_map($this->toArray(...), $qb->getQuery()->getResult());

        return $this->respond($this->paginated($items, $total, $pagination['page'], $pagination['perPage']));
    }

    #[Route('/api/snippet/{host}', name: 'pushword_api_snippet_create', methods: ['POST'])]
    public function create(string $host, Request $request): JsonResponse
    {
        $data = $this->decodeJson($request);
        $slug = \is_string($data['slug'] ?? null) ? $data['slug'] : null;
        if (null === $slug || '' === $slug) {
            return $this->badRequest('Missing slug');
        }

        $normalizedSlug = Snippet::normalizeSlug($slug);
        if (null !== $this->repository->findOneBy(['host' => $host, 'slug' => $normalizedSlug])) {
            return $this->respond(['error' => 'Slug already exists'], Response::HTTP_CONFLICT);
        }

        $snippet = new Snippet();
        $snippet->host = $host;
        $snippet->setSlug($slug);
        $this->apply($snippet, $data);

        $violations = $this->validator->validate($snippet);
        if (\count($violations) > 0) {
            return $this->validationErrors($violations);
        }

        $this->entityManager->persist($snippet);
        $this->entityManager->flush();

        return $this->respond($this->toArray($snippet), Response::HTTP_CREATED);
    }

    #[Route('/api/snippet/{host}/{slug}', name: 'pushword_api_snippet_item', methods: ['GET', 'PUT', 'DELETE'])]
    public function item(string $host, string $slug, Request $request): JsonResponse
    {
        $snippet = $this->repository->findOneBy(['host' => $host, 'slug' => Snippet::normalizeSlug($slug)]);
        if (! $snippet instanceof Snippet) {
            return $this->notFound('Snippet not found');
        }

        return match ($request->getMethod()) {
            'GET' => $this->respond($this->toArray($snippet)),
            'PUT' => $this->doUpdate($snippet, $request),
            'DELETE' => $this->doDelete($snippet),
            default => $this->respond(['error' => 'Method not allowed'], Response::HTTP_METHOD_NOT_ALLOWED),
        };
    }

    private function doUpdate(Snippet $snippet, Request $request): JsonResponse
    {
        $this->apply($snippet, $this->decodeJson($request));
        $violations = $this->validator->validate($snippet);
        if (\count($violations) > 0) {
            return $this->validationErrors($violations);
        }

        $this->entityManager->flush();

        return $this->respond($this->toArray($snippet));
    }

    private function doDelete(Snippet $snippet): JsonResponse
    {
        $this->entityManager->remove($snippet);
        $this->entityManager->flush();

        return $this->noContent();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function apply(Snippet $snippet, array $data): void
    {
        if (\array_key_exists('name', $data) && \is_string($data['name'])) {
            $snippet->setName($data['name']);
        }

        if (\array_key_exists('content', $data) && \is_string($data['content'])) {
            $snippet->setContent($data['content']);
        }

        if (\array_key_exists('tags', $data) && \is_array($data['tags'])) {
            /** @var list<string> $tags */
            $tags = array_values(array_filter($data['tags'], is_string(...)));
            $snippet->setTags($tags);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(Snippet $snippet): array
    {
        return [
            'host' => $snippet->host,
            'slug' => $snippet->getSlug(),
            'name' => $snippet->getName(),
            'content' => $snippet->getContent(),
            'tags' => $snippet->getTagList(),
            'createdAt' => $snippet->createdAt->format(DateTimeInterface::ATOM),
            'updatedAt' => $snippet->updatedAt->format(DateTimeInterface::ATOM),
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
                'host' => ['type' => 'string'],
                'slug' => ['type' => 'string'],
                'name' => ['type' => 'string'],
                'content' => ['type' => 'string'],
                'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
        ];

        return [
            'paths' => [
                '/api/snippet' => [
                    'get' => ['summary' => 'List snippets', 'responses' => ['200' => ['description' => 'OK']]],
                ],
                '/api/snippet/{host}' => [
                    'parameters' => [['name' => 'host', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
                    'post' => ['summary' => 'Create a snippet', 'responses' => ['201' => ['description' => 'Created']]],
                ],
                '/api/snippet/{host}/{slug}' => [
                    'parameters' => [
                        ['name' => 'host', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                        ['name' => 'slug', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                    ],
                    'get' => ['summary' => 'Get a snippet', 'responses' => ['200' => ['description' => 'OK']]],
                    'put' => ['summary' => 'Update a snippet', 'responses' => ['200' => ['description' => 'OK']]],
                    'delete' => ['summary' => 'Delete a snippet', 'responses' => ['204' => ['description' => 'Deleted']]],
                ],
            ],
            'components' => ['schemas' => ['Snippet' => $schema]],
        ];
    }
}
