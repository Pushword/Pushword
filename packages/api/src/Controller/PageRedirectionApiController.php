<?php

namespace Pushword\Api\Controller;

use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\ValueObject\PageRedirection;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Service\RevisionCalculator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EDITOR')]
final class PageRedirectionApiController extends AbstractApiController
{
    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly RevisionCalculator $revisions,
    ) {
    }

    #[Route('/api/redirection', name: 'pushword_api_redirection_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $pagination = $this->paginationParams($request);

        $qb = $this->pageRepository->createQueryBuilder('p')
            ->where('p.mainContent LIKE :prefix')
            ->setParameter('prefix', 'Location:%');

        if (null !== $request->query->get('host')) {
            $qb->andWhere('p.host = :host')->setParameter('host', $request->query->getString('host'));
        }

        $total = (int) (clone $qb)->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();

        $qb->orderBy('p.updatedAt', 'DESC')
            ->setFirstResult($pagination['offset'])
            ->setMaxResults($pagination['perPage']);

        /** @var list<Page> $items */
        $items = $qb->getQuery()->getResult();
        $payload = array_values(array_filter(
            array_map($this->toArray(...), $items),
            static fn (?array $row): bool => null !== $row,
        ));

        return $this->respond($this->paginated($payload, $total, $pagination['page'], $pagination['perPage']));
    }

    #[Route('/api/redirection/{host}', name: 'pushword_api_redirection_create', methods: ['POST'])]
    public function create(string $host, Request $request): JsonResponse
    {
        $data = $this->decodeJson($request);
        $slug = \is_string($data['slug'] ?? null) ? $data['slug'] : null;
        $target = \is_string($data['redirectTo'] ?? null) ? $data['redirectTo'] : null;
        $code = \is_int($data['code'] ?? null) ? $data['code'] : 301;

        if (null === $slug || '' === $slug || null === $target || '' === $target) {
            return $this->badRequest('Missing slug or redirectTo');
        }

        if (null !== $this->pageRepository->findOneBy(['host' => $host, 'slug' => Page::normalizeSlug($slug)])) {
            return $this->respond(['error' => 'Slug already exists'], Response::HTTP_CONFLICT);
        }

        $page = new Page();
        $page->host = $host;
        $page->setSlug($slug);
        $page->setMainContent(new PageRedirection($target, $code)->toContent());
        $page->editedBy = $this->getApiUser();
        $page->createdBy = $this->getApiUser();

        $this->entityManager->persist($page);
        $this->entityManager->flush();

        return $this->respond($this->toArray($page), Response::HTTP_CREATED);
    }

    #[Route('/api/redirection/{host}/{slug}', name: 'pushword_api_redirection_item', requirements: ['slug' => '.+'], methods: ['GET', 'PUT', 'DELETE'])]
    public function item(string $host, string $slug, Request $request): JsonResponse
    {
        $page = $this->pageRepository->findOneBy(['host' => $host, 'slug' => Page::normalizeSlug($slug)]);
        if (null === $page || null === PageRedirection::fromContent($page->getMainContent())) {
            return $this->notFound('Redirection not found');
        }

        return match ($request->getMethod()) {
            'GET' => $this->respond($this->toArray($page)),
            'PUT' => $this->doUpdate($page, $request),
            'DELETE' => $this->doDelete($page),
            default => $this->respond(['error' => 'Method not allowed'], Response::HTTP_METHOD_NOT_ALLOWED),
        };
    }

    private function doUpdate(Page $page, Request $request): JsonResponse
    {
        $conflict = $this->checkIfMatch(
            $request,
            $this->revisions->compute($page),
            fn (): array => $this->toArray($page) ?? [],
        );
        if (null !== $conflict) {
            return $conflict;
        }

        $data = $this->decodeJson($request);
        $target = \is_string($data['redirectTo'] ?? null) ? $data['redirectTo'] : null;
        $code = \is_int($data['code'] ?? null) ? $data['code'] : 301;
        if (null === $target || '' === $target) {
            return $this->badRequest('Missing redirectTo');
        }

        $page->setMainContent(new PageRedirection($target, $code)->toContent());
        $page->editedBy = $this->getApiUser();

        $this->entityManager->flush();

        return $this->respond($this->toArray($page));
    }

    private function doDelete(Page $page): JsonResponse
    {
        $this->entityManager->remove($page);
        $this->entityManager->flush();

        return $this->noContent();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function toArray(Page $page): ?array
    {
        $redirection = PageRedirection::fromContent($page->getMainContent());
        if (null === $redirection) {
            return null;
        }

        return [
            'host' => $page->host,
            'slug' => $page->getSlug(),
            'redirectTo' => $redirection->url,
            'code' => $redirection->code,
            'revision' => $this->revisions->compute($page),
            'updatedAt' => $page->updatedAt?->format(DateTimeInterface::ATOM),
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
                'redirectTo' => ['type' => 'string'],
                'code' => ['type' => 'integer', 'example' => 301],
                'revision' => ['type' => 'string'],
                'updatedAt' => ['type' => 'string', 'format' => 'date-time'],
            ],
        ];

        return [
            'paths' => [
                '/api/redirection' => [
                    'get' => ['summary' => 'List redirections', 'responses' => ['200' => ['description' => 'OK']]],
                ],
                '/api/redirection/{host}' => [
                    'post' => [
                        'summary' => 'Create a redirection',
                        'parameters' => [['name' => 'host', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
                        'requestBody' => ['content' => ['application/json' => ['schema' => [
                            'type' => 'object',
                            'properties' => [
                                'slug' => ['type' => 'string'],
                                'redirectTo' => ['type' => 'string'],
                                'code' => ['type' => 'integer'],
                            ],
                            'required' => ['slug', 'redirectTo'],
                        ]]]],
                        'responses' => ['201' => ['description' => 'Created'], '409' => ['description' => 'Slug already exists']],
                    ],
                ],
                '/api/redirection/{host}/{slug}' => [
                    'parameters' => [
                        ['name' => 'host', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                        ['name' => 'slug', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                    ],
                    'get' => ['summary' => 'Get redirection', 'responses' => ['200' => ['description' => 'OK'], '404' => ['description' => 'Not found']]],
                    'put' => [
                        'summary' => 'Update redirection',
                        'parameters' => [['name' => 'If-Match', 'in' => 'header', 'required' => true, 'schema' => ['type' => 'string']]],
                        'responses' => ['200' => ['description' => 'OK'], '409' => ['description' => 'Revision mismatch']],
                    ],
                    'delete' => ['summary' => 'Delete redirection', 'responses' => ['204' => ['description' => 'Deleted']]],
                ],
            ],
            'components' => ['schemas' => ['Redirection' => $schema]],
        ];
    }
}
