<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Controller;

use Pushword\Repurpose\Repository\SocialPostRepository;
use Pushword\Repurpose\Service\CarouselFactory;
use Pushword\Repurpose\Service\CreatorResolverInterface;
use Pushword\Repurpose\Service\SlideRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PUSHWORD_ADMIN')]
final class SocialPostPreviewController extends AbstractController
{
    public function __construct(
        private readonly SocialPostRepository $repository,
        private readonly CarouselFactory $factory,
        private readonly SlideRenderer $renderer,
        private readonly CreatorResolverInterface $creatorResolver,
    ) {
    }

    #[Route('/admin/repurpose/{id}/cover.svg', name: 'repurpose_cover', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function cover(int $id): Response
    {
        $post = $this->repository->find($id) ?? throw $this->createNotFoundException('Carousel not found.');
        $carousel = $this->factory->fromArray($post->spec);
        $svg = $this->renderer->renderSlide($carousel, 0, $this->creatorResolver->resolve($carousel, $post->host));
        if ('' === $svg) {
            throw $this->createNotFoundException('Carousel has no cover.');
        }

        return new Response($svg, Response::HTTP_OK, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
