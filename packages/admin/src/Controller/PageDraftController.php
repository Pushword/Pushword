<?php

declare(strict_types=1);

namespace Pushword\Admin\Controller;

use Pushword\Core\Collaboration\DraftExchange;
use Pushword\Core\Collaboration\LivePresence;
use Pushword\Core\Entity\Page;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EDITOR')]
final class PageDraftController extends AbstractController
{
    #[Route('/admin/page/{id}/draft', name: 'pushword_page_draft', requirements: ['id' => '\d+'], methods: ['POST', 'DELETE'])]
    public function sync(Page $page, Request $request, DraftExchange $exchange, LivePresence $presence): JsonResponse
    {
        if (! $this->isCsrfTokenValid('page-draft-'.$page->id, $request->headers->get('X-Collaboration-Token'))) {
            return new JsonResponse(['error' => 'invalid_token'], Response::HTTP_FORBIDDEN);
        }

        $request->getSession()->save();

        return $presence->sync($request, 'page:'.$page->id, static fn (): JsonResponse => $exchange->page($request, $page));
    }
}
