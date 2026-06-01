<?php

namespace Pushword\PageWorkflow\Controller\Admin;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\User;
use Pushword\Core\Utils\FlashBag;
use Pushword\PageWorkflow\Pending\PendingModification;
use Pushword\PageWorkflow\Pending\PendingModificationStorageInterface;
use Pushword\PageWorkflow\Pending\PendingPayload;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Workflow\Exception\LogicException as WorkflowLogicException;
use Symfony\Component\Workflow\Registry;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PendingModificationController extends AbstractController
{
    private const string CSRF_PENDING_EDIT = 'pw_page_pending_edit';

    private const string CSRF_PENDING_SAVE_COMPARE = 'pw_page_pending_save_compare';

    private const string CSRF_PENDING_TRANSITION = 'pw_page_pending_transition';

    private const string CSRF_PENDING_DISCARD = 'pw_page_pending_discard';

    public function __construct(
        private readonly PendingModificationStorageInterface $storage,
        private readonly Registry $workflowRegistry,
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Edit the pending modification: pre-fills from the existing pending if any,
     * otherwise from the current live page. Submitting writes to the storage and
     * never touches the Page row.
     */
    #[Route(path: '/admin/page/{id}/pending/edit', name: 'pushword_page_pending_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Page $page): Response
    {
        $modification = $this->loadOrInitialize($page);

        if ($request->isMethod('POST')) {
            if (null !== $csrfError = $this->checkCsrf($request, self::CSRF_PENDING_EDIT)) {
                return $csrfError;
            }

            $payload = [];
            foreach (PendingPayload::FIELDS as $field) {
                $payload[$field] = (string) $request->request->get($field, '');
            }

            $modification->payload = $payload;
            $modification->editMessage = (string) $request->request->get('editMessage', '');
            $this->stampEditor($modification);

            $this->storage->write($page, $modification);

            FlashBag::get($request)?->add('success', $this->translator->trans('adminPagePendingSaved'));

            return $this->redirectToRoute('pushword_page_pending_compare', ['id' => $page->id]);
        }

        return $this->render('@PushwordPageWorkflow/admin/pending_edit.html.twig', [
            'page' => $page,
            'modification' => $modification,
            'fields' => PendingPayload::FIELDS,
            'csrfTokenId' => self::CSRF_PENDING_EDIT,
        ]);
    }

    #[Route(path: '/admin/page/{id}/pending/compare', name: 'pushword_page_pending_compare', methods: ['GET'])]
    public function compare(Request $request, Page $page): Response
    {
        $modification = $this->storage->read($page);
        if (null === $modification) {
            FlashBag::get($request)?->add('warning', $this->translator->trans('adminPagePendingNotFound'));

            return $this->redirectToRoute('pushword_page_pending_edit', ['id' => $page->id]);
        }

        $live = PendingPayload::snapshotFromPage($page);

        $workflow = $this->workflowRegistry->get($modification, 'page_pending_modification');
        $transitions = [];
        foreach ($workflow->getEnabledTransitions($modification) as $transition) {
            $transitions[] = $transition->getName();
        }

        return $this->render('@PushwordPageWorkflow/admin/pending_compare.html.twig', [
            'page' => $page,
            'modification' => $modification,
            'live' => $live,
            'pending' => $modification->payload,
            'fields' => PendingPayload::FIELDS,
            'transitions' => $transitions,
            'saveCsrfTokenId' => self::CSRF_PENDING_SAVE_COMPARE,
            'transitionCsrfTokenId' => self::CSRF_PENDING_TRANSITION,
            'discardCsrfTokenId' => self::CSRF_PENDING_DISCARD,
        ]);
    }

    /**
     * Save back the diff edits from the compare view to the pending storage.
     * Field values are read from POST body; mainContent comes from a hidden
     * textarea populated by the Monaco modified editor.
     */
    #[Route(path: '/admin/page/{id}/pending/save-compare', name: 'pushword_page_pending_save_compare', methods: ['POST'])]
    public function saveCompare(Request $request, Page $page): Response
    {
        if (null !== $csrfError = $this->checkCsrf($request, self::CSRF_PENDING_SAVE_COMPARE)) {
            return $csrfError;
        }

        $modification = $this->loadOrInitialize($page);

        $payload = $modification->payload;
        foreach (PendingPayload::FIELDS as $field) {
            if ($request->request->has($field)) {
                $payload[$field] = (string) $request->request->get($field, '');
            }
        }

        $modification->payload = $payload;
        $editMessage = $request->request->get('editMessage');
        if (is_string($editMessage)) {
            $modification->editMessage = $editMessage;
        }

        $this->stampEditor($modification);
        $this->storage->write($page, $modification);

        FlashBag::get($request)?->add('success', $this->translator->trans('adminPagePendingSaved'));

        return $this->redirectToRoute('pushword_page_pending_compare', ['id' => $page->id]);
    }

    #[Route(path: '/admin/page/{id}/pending/transition/{transition}', name: 'pushword_page_pending_transition', methods: ['POST'])]
    public function transition(Request $request, Page $page, string $transition): Response
    {
        if (null !== $csrfError = $this->checkCsrf($request, self::CSRF_PENDING_TRANSITION.':'.$transition)) {
            return $csrfError;
        }

        $modification = $this->storage->read($page);
        if (null === $modification) {
            FlashBag::get($request)?->add('warning', $this->translator->trans('adminPagePendingNotFound'));

            return $this->redirectToRoute('pushword_page_pending_edit', ['id' => $page->id]);
        }

        $workflow = $this->workflowRegistry->get($modification, 'page_pending_modification');

        if (! $workflow->can($modification, $transition)) {
            FlashBag::get($request)?->add('danger', $this->translator->trans('adminPagePendingTransitionDenied'));

            return $this->redirectToRoute('pushword_page_pending_compare', ['id' => $page->id]);
        }

        try {
            $workflow->apply($modification, $transition);
        } catch (WorkflowLogicException) {
            FlashBag::get($request)?->add('danger', $this->translator->trans('adminPagePendingTransitionDenied'));

            return $this->redirectToRoute('pushword_page_pending_compare', ['id' => $page->id]);
        }

        if ('approved' !== $modification->workflowState) {
            $this->storage->write($page, $modification);
        } else {
            PendingPayload::applyOnPage($page, $modification->payload);
            $this->em->flush();
            $this->storage->delete($page);
        }

        FlashBag::get($request)?->add('success', $this->translator->trans('adminPagePendingTransitionApplied'));

        return $this->redirectToRoute('pushword_page_pending_compare', ['id' => $page->id]);
    }

    #[Route(path: '/admin/page/{id}/pending/discard', name: 'pushword_page_pending_discard', methods: ['POST'])]
    public function discard(Request $request, Page $page): Response
    {
        if (null !== $csrfError = $this->checkCsrf($request, self::CSRF_PENDING_DISCARD)) {
            return $csrfError;
        }

        $this->storage->delete($page);
        FlashBag::get($request)?->add('success', $this->translator->trans('adminPagePendingDiscarded'));

        $referer = $request->headers->get('referer');

        return $this->redirect(is_string($referer) && '' !== $referer ? $referer : '/admin');
    }

    private function loadOrInitialize(Page $page): PendingModification
    {
        return $this->storage->read($page) ?? new PendingModification(
            pageId: (int) $page->id,
            payload: PendingPayload::snapshotFromPage($page),
        );
    }

    private function checkCsrf(Request $request, string $tokenId): ?Response
    {
        if ($this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'))) {
            return null;
        }

        return new Response('Invalid CSRF token.', Response::HTTP_FORBIDDEN);
    }

    private function stampEditor(PendingModification $modification): void
    {
        $user = $this->security->getUser();
        if ($user instanceof User) {
            $modification->editedBy = $user->id;
        }

        $modification->editedAt = new DateTime();
    }
}
