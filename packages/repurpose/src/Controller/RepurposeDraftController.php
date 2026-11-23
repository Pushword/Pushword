<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Controller;

use Pushword\Repurpose\Entity\SocialPost;
use Pushword\Repurpose\Service\CarouselLiveEditing;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PUSHWORD_ADMIN')]
final class RepurposeDraftController extends AbstractController
{
    #[Route('/admin/repurpose/studio/{id}/draft', name: 'repurpose_studio_draft', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function sync(SocialPost $post, Request $request, CarouselLiveEditing $liveEditing): JsonResponse
    {
        if (! $this->isCsrfTokenValid('repurpose-draft-'.$post->id, $request->headers->get('X-Collaboration-Token'))) {
            return new JsonResponse(['error' => 'invalid_token'], Response::HTTP_FORBIDDEN);
        }

        $request->getSession()->save();

        return $liveEditing->sync($request, $post);
    }
}
