<?php

namespace Pushword\PageWorkflow\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Entity\Page;
use Pushword\Core\Utils\FlashBag;
use Pushword\PageWorkflow\Repository\PageEditorialStateRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Workflow\Exception\LogicException as WorkflowLogicException;
use Symfony\Component\Workflow\Registry;
use Symfony\Contracts\Translation\TranslatorInterface;

final class WorkflowTransitionController extends AbstractController
{
    public const string CSRF_TOKEN_ID = 'pw_page_workflow_transition';

    public function __construct(
        private readonly Registry $workflowRegistry,
        private readonly PageEditorialStateRepository $editorialStateRepo,
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Admin-protected POST route. The workflow guard expressions enforce per-transition
     * authorization (e.g. "is_granted('ROLE_EDITOR')" on `approve`).
     * CSRF token id is namespaced by transition so a leaked token can't be replayed
     * against a different transition.
     */
    #[Route(path: '/admin/page/{id}/workflow/{transition}', name: 'pushword_page_workflow', methods: ['POST'])]
    public function apply(Request $request, Page $page, string $transition): Response
    {
        if (! $this->isCsrfTokenValid(self::CSRF_TOKEN_ID.':'.$transition, (string) $request->request->get('_token'))) {
            return new Response('Invalid CSRF token.', Response::HTTP_FORBIDDEN);
        }

        $state = $this->editorialStateRepo->findOrCreateFor($page);
        $this->em->flush();

        $workflow = $this->workflowRegistry->get($state, 'page_editorial');

        if ($workflow->can($state, $transition)) {
            try {
                $workflow->apply($state, $transition);
                $this->em->flush();
                FlashBag::get($request)?->add('success', $this->translator->trans('adminPageWorkflowApplied'));
            } catch (WorkflowLogicException) {
                FlashBag::get($request)?->add('danger', $this->translator->trans('adminPageWorkflowDenied'));
            }
        } else {
            FlashBag::get($request)?->add('danger', $this->translator->trans('adminPageWorkflowDenied'));
        }

        $referer = $request->headers->get('referer');

        return $this->redirect(is_string($referer) && '' !== $referer ? $referer : '/admin');
    }
}
