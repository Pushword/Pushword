<?php

namespace Pushword\Api\Controller;

use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Pushword\Api\Service\BodyPatcher;
use Pushword\Api\Service\BodyPatchException;
use Pushword\Api\Service\PageFrontmatterMapper;
use Pushword\Api\Workflow\WorkflowGateInterface;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Service\Markdown\MarkdownParser;
use Pushword\Core\Service\RevisionCalculator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[IsGranted('ROLE_EDITOR')]
final class PageApiController extends AbstractApiController
{
    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly PageFrontmatterMapper $mapper,
        private readonly RevisionCalculator $revisions,
        private readonly ValidatorInterface $validator,
        private readonly MarkdownParser $markdownParser,
        private readonly BodyPatcher $bodyPatcher,
        private readonly string $deleteStrategy = 'soft',
        private readonly ?WorkflowGateInterface $workflowGate = null,
    ) {
    }

    #[Route('/api/page/search', name: 'pushword_api_page_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $pagination = $this->paginationParams($request);

        $qb = $this->pageRepository->createQueryBuilder('p');

        if (null !== $request->query->get('host')) {
            $qb->andWhere('p.host = :host')->setParameter('host', $request->query->getString('host'));
        }

        if (null !== $request->query->get('locale')) {
            $qb->andWhere('p.locale = :locale')->setParameter('locale', $request->query->getString('locale'));
        }

        if (null !== $request->query->get('parentPage')) {
            $qb->leftJoin('p.parentPage', 'parent')
                ->andWhere('parent.slug = :parentSlug')
                ->setParameter('parentSlug', $request->query->getString('parentPage'));
        }

        if (null !== $request->query->get('q')) {
            $qb->andWhere('p.h1 LIKE :q OR p.slug LIKE :q OR p.title LIKE :q OR p.mainContent LIKE :q')
                ->setParameter('q', '%'.$request->query->getString('q').'%');
        }

        $tags = $request->query->all('tag');
        foreach ($tags as $i => $tag) {
            if (! \is_string($tag)) {
                continue;
            }

            $qb->andWhere('p.tags LIKE :tag'.$i)->setParameter('tag'.$i, '%'.$tag.'%');
        }

        $totalQb = clone $qb;
        $total = (int) $totalQb->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();

        $qb->orderBy('p.updatedAt', 'DESC')
            ->setFirstResult($pagination['offset'])
            ->setMaxResults($pagination['perPage']);

        /** @var list<Page> $items */
        $items = $qb->getQuery()->getResult();
        $payload = array_map($this->mapper->summary(...), $items);

        return $this->respond($this->paginated($payload, $total, $pagination['page'], $pagination['perPage']));
    }

    #[Route('/api/page/preview', name: 'pushword_api_page_preview', methods: ['POST'])]
    public function preview(Request $request): JsonResponse
    {
        $data = $this->decodeJson($request);
        $host = \is_string($data['host'] ?? null) ? $data['host'] : '';
        $slug = \is_string($data['slug'] ?? null) ? $data['slug'] : 'preview';
        $frontmatter = $this->extractFrontmatter($data);
        $body = \is_string($data['body'] ?? null) ? $data['body'] : '';

        $page = $this->mapper->buildTransient($host, $slug, $frontmatter, $body);

        return $this->respond([
            'host' => $page->host,
            'slug' => $page->getSlug(),
            'html' => $this->markdownParser->transform($page->getMainContent()),
            'frontmatter' => $this->mapper->toArray($page)['frontmatter'],
        ]);
    }

    #[Route('/api/page/{host}', name: 'pushword_api_page_create', methods: ['POST'])]
    public function create(string $host, Request $request): JsonResponse
    {
        $data = $this->decodeJson($request);
        $frontmatter = $this->extractFrontmatter($data);
        $body = \is_string($data['body'] ?? null) ? $data['body'] : null;

        $slug = \is_string($frontmatter['slug'] ?? null) ? $frontmatter['slug'] : null;
        if (null === $slug || '' === $slug) {
            return $this->badRequest('Missing slug in frontmatter');
        }

        if (null !== $this->pageRepository->findOneBy(['host' => $host, 'slug' => Page::normalizeSlug($slug)])) {
            return $this->respond(['error' => 'Page already exists'], Response::HTTP_CONFLICT);
        }

        $page = new Page();
        $page->setSlug($slug);

        $this->mapper->applyFrontmatter($page, $frontmatter);
        if (null !== $body) {
            $page->setMainContent($body);
        }

        // URL host wins over any host field in the frontmatter payload.
        $page->host = $host;
        $page->editedBy = $this->getApiUser();
        $page->createdBy = $this->getApiUser();

        $violations = $this->validator->validate($page);
        if (\count($violations) > 0) {
            return $this->validationErrors($violations);
        }

        $this->entityManager->persist($page);
        $this->entityManager->flush();

        return $this->writeResponse(
            $request,
            $this->buildMinimalPayload($page),
            fn (): array => $this->buildPagePayload($page),
            $this->revisions->compute($page),
            Response::HTTP_CREATED,
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function extractFrontmatter(array $data): array
    {
        if (! \is_array($data['frontmatter'] ?? null)) {
            return [];
        }

        /** @var array<string, mixed> $frontmatter */
        $frontmatter = $data['frontmatter'];

        return $frontmatter;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<array<string, mixed>>
     */
    private function extractEdits(array $data): array
    {
        if (! \is_array($data['edits'] ?? null)) {
            return [];
        }

        $edits = [];
        foreach ($data['edits'] as $edit) {
            if (\is_array($edit)) {
                /** @var array<string, mixed> $edit */
                $edits[] = $edit;
            }
        }

        return $edits;
    }

    #[Route('/api/page/{host}/{slug}', name: 'pushword_api_page_item', requirements: ['slug' => '.+'], methods: ['GET', 'PUT', 'PATCH', 'DELETE'])]
    public function item(string $host, string $slug, Request $request): JsonResponse
    {
        $page = $this->findPage($host, $slug);
        if (null === $page) {
            return $this->notFound('Page not found');
        }

        return match ($request->getMethod()) {
            'GET' => $this->doGet($page),
            'PUT' => $this->doUpdate($page, $request),
            'PATCH' => $this->doPatch($page, $request),
            'DELETE' => $this->doDelete($page),
            default => $this->respond(['error' => 'Method not allowed'], Response::HTTP_METHOD_NOT_ALLOWED),
        };
    }

    private function doGet(Page $page): JsonResponse
    {
        return $this->respond($this->buildPagePayload($page), Response::HTTP_OK, ['ETag' => $this->revisions->compute($page)]);
    }

    private function doUpdate(Page $page, Request $request): JsonResponse
    {
        $conflict = $this->checkIfMatch($request, $this->revisions->compute($page), fn (): array => $this->buildPagePayload($page));
        if (null !== $conflict) {
            return $conflict;
        }

        $data = $this->decodeJson($request);
        $frontmatter = $this->extractFrontmatter($data);
        $body = \is_string($data['body'] ?? null) ? $data['body'] : null;

        return $this->applyAndRespond($page, $frontmatter, $body, $request);
    }

    private function doPatch(Page $page, Request $request): JsonResponse
    {
        $conflict = $this->checkIfMatch($request, $this->revisions->compute($page), fn (): array => $this->buildPagePayload($page));
        if (null !== $conflict) {
            return $conflict;
        }

        $data = $this->decodeJson($request);
        $frontmatter = $this->extractFrontmatter($data);
        $edits = $this->extractEdits($data);

        if ([] === $edits && [] === $frontmatter) {
            return $this->badRequest('Provide at least one of: edits, frontmatter');
        }

        try {
            $body = [] === $edits ? null : $this->bodyPatcher->apply($page->getMainContent(), $edits);
        } catch (BodyPatchException $bodyPatchException) {
            return $this->respond([
                'error' => 'patch_failed',
                'edit' => [
                    'index' => $bodyPatchException->index,
                    'reason' => $bodyPatchException->reason,
                    'matches' => $bodyPatchException->matches,
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->applyAndRespond($page, $frontmatter, $body, $request);
    }

    /**
     * Shared tail of PUT and PATCH: route through the optional workflow gate
     * (202), else merge frontmatter + body, validate, persist and return the
     * token-efficient write response.
     *
     * @param array<string, mixed> $frontmatter
     */
    private function applyAndRespond(Page $page, array $frontmatter, ?string $body, Request $request): JsonResponse
    {
        if (null !== $this->workflowGate) {
            $result = $this->workflowGate->intercept($page, $frontmatter, $body, $this->getApiUser());
            if ($result['routed']) {
                return $this->respond([
                    'pendingModification' => [
                        'id' => $result['pendingId'] ?? null,
                        'state' => $result['state'] ?? null,
                    ],
                    'page' => $this->buildPagePayload($page),
                ], Response::HTTP_ACCEPTED);
            }
        }

        $this->mapper->applyFrontmatter($page, $frontmatter);
        if (null !== $body) {
            $page->setMainContent($body);
        }

        $page->editedBy = $this->getApiUser();

        $violations = $this->validator->validate($page);
        if (\count($violations) > 0) {
            return $this->validationErrors($violations);
        }

        $this->entityManager->flush();

        return $this->writeResponse(
            $request,
            $this->buildMinimalPayload($page),
            fn (): array => $this->buildPagePayload($page),
            $this->revisions->compute($page),
        );
    }

    private function doDelete(Page $page): JsonResponse
    {
        if ('hard' === $this->deleteStrategy) {
            $this->entityManager->remove($page);
        } else {
            $page->setPublishedAt(null);
            $page->editedBy = $this->getApiUser();
        }

        $this->entityManager->flush();

        return $this->noContent();
    }

    private function findPage(string $host, string $slug): ?Page
    {
        return $this->pageRepository->findOneBy([
            'host' => $host,
            'slug' => Page::normalizeSlug($slug),
        ]);
    }

    /**
     * Minimal write-response body: just enough for the client to track the new
     * revision without re-emitting the (potentially large) Markdown body.
     *
     * @return array<string, mixed>
     */
    private function buildMinimalPayload(Page $page): array
    {
        return [
            'host' => $page->host,
            'slug' => $page->getSlug(),
            'revision' => $this->revisions->compute($page),
            'updatedAt' => $page->updatedAt?->format(DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPagePayload(Page $page): array
    {
        $shape = $this->mapper->toArray($page);
        $editorial = $this->workflowGate?->describeEditorial($page);
        if (null !== $editorial) {
            $shape['frontmatter']['editorial'] = $editorial;
        }

        return [
            'host' => $page->host,
            'slug' => $page->getSlug(),
            'revision' => $this->revisions->compute($page),
            'frontmatter' => $shape['frontmatter'],
            'body' => $shape['body'],
            'updatedBy' => $page->editedBy?->email,
            'updatedAt' => $page->updatedAt?->format(DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function describe(): array
    {
        $pageSchema = [
            'type' => 'object',
            'properties' => [
                'host' => ['type' => 'string'],
                'slug' => ['type' => 'string'],
                'revision' => ['type' => 'string', 'description' => 'Opaque ETag value'],
                'frontmatter' => [
                    'type' => 'object',
                    'description' => 'Page metadata mirroring the flat-file YAML frontmatter',
                    'additionalProperties' => true,
                ],
                'body' => ['type' => 'string', 'description' => 'Page mainContent (Markdown)'],
                'updatedBy' => ['type' => 'string', 'nullable' => true],
                'updatedAt' => ['type' => 'string', 'format' => 'date-time'],
            ],
        ];

        $writeBody = [
            'application/json' => ['schema' => [
                'type' => 'object',
                'properties' => [
                    'frontmatter' => ['type' => 'object', 'additionalProperties' => true],
                    'body' => ['type' => 'string'],
                ],
            ]],
        ];

        $patchBody = [
            'application/json' => ['schema' => ['$ref' => '#/components/schemas/PagePatch']],
        ];

        // Opt out of the token-efficient minimal write response.
        $returnParam = [
            'name' => 'return',
            'in' => 'query',
            'schema' => ['type' => 'string', 'enum' => ['minimal', 'full'], 'default' => 'minimal'],
            'description' => 'Write responses return {host, slug, revision, updatedAt} by default; use `full` for the complete Page payload.',
        ];

        return [
            'paths' => [
                '/api/page/search' => [
                    'get' => [
                        'summary' => 'Search pages',
                        'parameters' => [
                            ['name' => 'host', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'q', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'locale', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'parentPage', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'tag', 'in' => 'query', 'schema' => ['type' => 'array', 'items' => ['type' => 'string']]],
                            ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer']],
                            ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer']],
                        ],
                        'responses' => ['200' => ['description' => 'Paginated search results']],
                    ],
                ],
                '/api/page/preview' => [
                    'post' => [
                        'summary' => 'Render Markdown body without persisting',
                        'requestBody' => ['content' => $writeBody],
                        'responses' => ['200' => ['description' => 'Rendered HTML + frontmatter echo']],
                    ],
                ],
                '/api/page/{host}' => [
                    'parameters' => [['name' => 'host', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
                    'post' => [
                        'summary' => 'Create a page',
                        'parameters' => [$returnParam],
                        'requestBody' => ['content' => $writeBody],
                        'responses' => [
                            '201' => ['description' => 'Created (minimal write response by default, full Page with ?return=full)'],
                            '409' => ['description' => 'Slug already exists'],
                            '422' => ['description' => 'Validation error'],
                        ],
                    ],
                ],
                '/api/page/{host}/{slug}' => [
                    'parameters' => [
                        ['name' => 'host', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                        ['name' => 'slug', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                    ],
                    'get' => [
                        'summary' => 'Get a page',
                        'responses' => [
                            '200' => ['description' => 'Page', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Page']]]],
                            '404' => ['description' => 'Not found'],
                        ],
                    ],
                    'put' => [
                        'summary' => 'Replace a page body/frontmatter (optimistic concurrency)',
                        'parameters' => [
                            ['name' => 'If-Match', 'in' => 'header', 'required' => true, 'schema' => ['type' => 'string']],
                            $returnParam,
                        ],
                        'requestBody' => ['content' => $writeBody],
                        'responses' => [
                            '200' => ['description' => 'Updated (minimal write response by default, full Page with ?return=full)'],
                            '202' => ['description' => 'Routed to PendingModification via page-workflow'],
                            '409' => ['description' => 'Revision mismatch, includes fresh page'],
                            '422' => ['description' => 'Validation error'],
                            '428' => ['description' => 'Missing If-Match header'],
                        ],
                    ],
                    'patch' => [
                        'summary' => 'Token-efficient partial edit: anchored find/replace on the body and/or selective frontmatter merge',
                        'parameters' => [
                            ['name' => 'If-Match', 'in' => 'header', 'required' => true, 'schema' => ['type' => 'string']],
                            $returnParam,
                        ],
                        'requestBody' => ['content' => $patchBody],
                        'responses' => [
                            '200' => ['description' => 'Patched (minimal write response by default, full Page with ?return=full)'],
                            '202' => ['description' => 'Routed to PendingModification via page-workflow'],
                            '400' => ['description' => 'Neither edits nor frontmatter provided'],
                            '409' => ['description' => 'Revision mismatch, includes fresh page'],
                            '422' => ['description' => 'patch_failed (find not_found/ambiguous) or validation error'],
                            '428' => ['description' => 'Missing If-Match header'],
                        ],
                    ],
                    'delete' => [
                        'summary' => 'Delete a page (soft/hard via config)',
                        'responses' => ['204' => ['description' => 'Deleted']],
                    ],
                ],
            ],
            'components' => [
                'schemas' => [
                    'Page' => $pageSchema,
                    'PageEdit' => [
                        'type' => 'object',
                        'description' => 'A single anchored find/replace edit applied to the Markdown body.',
                        'required' => ['find', 'replace'],
                        'properties' => [
                            'find' => ['type' => 'string', 'description' => 'Exact text to locate; must match once unless replaceAll is true'],
                            'replace' => ['type' => 'string'],
                            'replaceAll' => ['type' => 'boolean', 'default' => false],
                        ],
                    ],
                    'PagePatch' => [
                        'type' => 'object',
                        'description' => 'Partial update: at least one of edits or frontmatter is required.',
                        'properties' => [
                            'edits' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/PageEdit']],
                            'frontmatter' => ['type' => 'object', 'additionalProperties' => true],
                        ],
                    ],
                ],
            ],
        ];
    }
}
