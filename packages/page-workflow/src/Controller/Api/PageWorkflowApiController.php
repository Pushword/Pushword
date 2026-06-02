<?php

namespace Pushword\PageWorkflow\Controller\Api;

use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Pushword\Api\Controller\AbstractApiController;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\PageWorkflow\Pending\PendingModificationStorageInterface;
use Pushword\PageWorkflow\Pending\PendingPayload;
use Pushword\PageWorkflow\Repository\PageEditorialStateRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Workflow\Exception\LogicException as WorkflowLogicException;
use Symfony\Component\Workflow\Registry;

#[IsGranted('ROLE_EDITOR')]
final class PageWorkflowApiController extends AbstractApiController
{
    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly PendingModificationStorageInterface $storage,
        private readonly PageEditorialStateRepository $editorialStateRepo,
        private readonly Registry $workflowRegistry,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/api/page/{host}/{slug}/transition', name: 'pushword_api_page_workflow_transition', requirements: ['slug' => '.+'], methods: ['POST'])]
    public function transition(string $host, string $slug, Request $request): JsonResponse
    {
        $page = $this->pageRepository->findOneBy(['host' => $host, 'slug' => Page::normalizeSlug($slug)]);
        if (null === $page) {
            return $this->notFound('Page not found');
        }

        $data = $this->decodeJson($request);
        $transition = \is_string($data['transition'] ?? null) ? $data['transition'] : '';
        if ('' === $transition) {
            return $this->badRequest('Missing transition');
        }

        $state = $this->editorialStateRepo->findOrCreateFor($page);
        $this->em->flush();
        $workflow = $this->workflowRegistry->get($state, 'page_editorial');

        if (! $workflow->can($state, $transition)) {
            return $this->respond([
                'error' => 'transition_denied',
                'state' => $state->getWorkflowState(),
            ], Response::HTTP_CONFLICT);
        }

        try {
            $workflow->apply($state, $transition);
            $this->em->flush();
        } catch (WorkflowLogicException $workflowLogicException) {
            return $this->respond(['error' => 'transition_failed', 'message' => $workflowLogicException->getMessage()], Response::HTTP_CONFLICT);
        }

        return $this->respond([
            'host' => $host,
            'slug' => $slug,
            'state' => $state->getWorkflowState(),
        ]);
    }

    #[Route('/api/page-workflow/pending', name: 'pushword_api_page_workflow_pending_list', methods: ['GET'])]
    public function pendingList(Request $request): JsonResponse
    {
        $pagination = $this->paginationParams($request);
        $qb = $this->pageRepository->createQueryBuilder('p');
        $totalAll = (int) (clone $qb)->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();
        $qb->setFirstResult($pagination['offset'])->setMaxResults($pagination['perPage']);

        $items = [];
        foreach ($qb->getQuery()->getResult() as $page) {
            if (! $page instanceof Page) {
                continue;
            }

            $modification = $this->storage->read($page);
            if (null === $modification) {
                continue;
            }

            $items[] = [
                'pageId' => $modification->pageId,
                'host' => $page->host,
                'slug' => $page->getSlug(),
                'state' => $modification->workflowState,
                'editedAt' => $modification->editedAt->format(DateTimeInterface::ATOM),
                'editMessage' => $modification->editMessage,
                'payload' => $modification->payload,
            ];
        }

        return $this->respond($this->paginated($items, $totalAll, $pagination['page'], $pagination['perPage']));
    }

    #[Route('/api/page-workflow/pending/{pageId}/apply', name: 'pushword_api_page_workflow_pending_apply', methods: ['POST'])]
    public function pendingApply(int $pageId): JsonResponse
    {
        $page = $this->pageRepository->find($pageId);
        if (! $page instanceof Page) {
            return $this->notFound('Page not found');
        }

        $modification = $this->storage->read($page);
        if (null === $modification) {
            return $this->notFound('No pending modification');
        }

        if ('approved' !== $modification->workflowState) {
            return $this->respond([
                'error' => 'not_approved',
                'state' => $modification->workflowState,
            ], Response::HTTP_CONFLICT);
        }

        PendingPayload::applyOnPage($page, $modification->payload);
        $this->storage->delete($page);
        $this->em->flush();

        return $this->respond(['host' => $page->host, 'slug' => $page->getSlug(), 'applied' => true]);
    }

    /**
     * Drive the pending modification's own workflow (draft → in_review →
     * approved). Mirrors the admin PendingModificationController: when the
     * transition reaches `approved`, the snapshot is applied onto the live Page
     * and the pending storage is cleared.
     */
    #[Route('/api/page-workflow/pending/{pageId}/transition', name: 'pushword_api_page_workflow_pending_transition', methods: ['POST'])]
    public function pendingTransition(int $pageId, Request $request): JsonResponse
    {
        $page = $this->pageRepository->find($pageId);
        if (! $page instanceof Page) {
            return $this->notFound('Page not found');
        }

        $modification = $this->storage->read($page);
        if (null === $modification) {
            return $this->notFound('No pending modification');
        }

        $data = $this->decodeJson($request);
        $transition = \is_string($data['transition'] ?? null) ? $data['transition'] : '';
        if ('' === $transition) {
            return $this->badRequest('Missing transition');
        }

        $workflow = $this->workflowRegistry->get($modification, 'page_pending_modification');
        if (! $workflow->can($modification, $transition)) {
            return $this->respond([
                'error' => 'transition_denied',
                'state' => $modification->workflowState,
            ], Response::HTTP_CONFLICT);
        }

        try {
            $workflow->apply($modification, $transition);
        } catch (WorkflowLogicException $workflowLogicException) {
            return $this->respond(['error' => 'transition_failed', 'message' => $workflowLogicException->getMessage()], Response::HTTP_CONFLICT);
        }

        $applied = false;
        if ('approved' === $modification->workflowState) {
            PendingPayload::applyOnPage($page, $modification->payload);
            $this->storage->delete($page);
            $this->em->flush();
            $applied = true;
        } else {
            $this->storage->write($page, $modification);
        }

        return $this->respond([
            'host' => $page->host,
            'slug' => $page->getSlug(),
            'state' => $modification->workflowState,
            'applied' => $applied,
        ]);
    }

    #[Route('/api/page-workflow/pending/{pageId}', name: 'pushword_api_page_workflow_pending_delete', methods: ['DELETE'])]
    public function pendingDelete(int $pageId): JsonResponse
    {
        $page = $this->pageRepository->find($pageId);
        if (! $page instanceof Page) {
            return $this->notFound('Page not found');
        }

        $this->storage->delete($page);
        $this->em->flush();

        return $this->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    public static function describe(): array
    {
        return [
            'paths' => [
                '/api/page/{host}/{slug}/transition' => [
                    'post' => [
                        'summary' => 'Apply an editorial workflow transition (draft → in_review → approved)',
                        'parameters' => [
                            ['name' => 'host', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                            ['name' => 'slug', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                        ],
                        'requestBody' => ['content' => ['application/json' => ['schema' => [
                            'type' => 'object',
                            'properties' => ['transition' => ['type' => 'string', 'enum' => ['submit', 'approve', 'reject']]],
                            'required' => ['transition'],
                        ]]]],
                        'responses' => ['200' => ['description' => 'Transition applied'], '409' => ['description' => 'Transition denied']],
                    ],
                ],
                '/api/page-workflow/pending' => [
                    'get' => ['summary' => 'List pending modifications', 'responses' => ['200' => ['description' => 'Paginated list']]],
                ],
                '/api/page-workflow/pending/{pageId}/apply' => [
                    'post' => [
                        'summary' => 'Apply an approved pending modification onto the Page',
                        'parameters' => [['name' => 'pageId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                        'responses' => ['200' => ['description' => 'Applied'], '409' => ['description' => 'Not approved yet']],
                    ],
                ],
                '/api/page-workflow/pending/{pageId}/transition' => [
                    'post' => [
                        'summary' => 'Transition a pending modification (submit → in_review → approve → approved); applies onto the Page on approval',
                        'parameters' => [['name' => 'pageId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                        'requestBody' => ['content' => ['application/json' => ['schema' => [
                            'type' => 'object',
                            'properties' => ['transition' => ['type' => 'string', 'enum' => ['submit', 'approve', 'request_changes']]],
                            'required' => ['transition'],
                        ]]]],
                        'responses' => ['200' => ['description' => 'Transition applied'], '409' => ['description' => 'Transition denied']],
                    ],
                ],
                '/api/page-workflow/pending/{pageId}' => [
                    'delete' => [
                        'summary' => 'Reject and discard a pending modification',
                        'parameters' => [['name' => 'pageId', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                        'responses' => ['204' => ['description' => 'Discarded']],
                    ],
                ],
            ],
        ];
    }
}
