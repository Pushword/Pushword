<?php

namespace Pushword\Repurpose\Admin;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Pushword\Core\Repository\PageRepository;
use Pushword\Repurpose\Entity\SocialPost;
use Pushword\Repurpose\Repository\SocialPostRepository;
use Pushword\Repurpose\Service\CarouselDrafter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The "Repurpose" entry point on a page: if the page has no carousel yet, draft
 * one from its content and open the studio; if it already has some, jump to its
 * list of carousels (one row per network). Wired only when EasyAdmin is present.
 */
#[IsGranted('ROLE_PUSHWORD_ADMIN')]
final class RepurposeFromPageController extends AbstractController
{
    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly SocialPostRepository $socialPosts,
        private readonly CarouselDrafter $drafter,
        private readonly EntityManagerInterface $entityManager,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    #[AdminRoute(path: '/repurpose/from-page/{id}', name: 'repurpose_from_page', options: ['requirements' => ['id' => '\d+']])]
    public function fromPage(int $id): RedirectResponse
    {
        $page = $this->pageRepository->find($id) ?? throw new NotFoundHttpException('Page not found.');
        $host = $page->host;
        $slug = $page->getSlug();

        if ([] !== $this->socialPosts->findBy(['host' => $host, 'page' => $slug])) {
            return $this->redirect(
                $this->adminUrlGenerator
                    ->setController(SocialPostCrudController::class)
                    ->setAction('index')
                    ->set('query', $slug)
                    ->generateUrl(),
            );
        }

        $post = new SocialPost();
        $post->host = $host;
        $post->setSpec($this->drafter->draft($page));

        $this->entityManager->persist($post);
        $this->entityManager->flush();

        return $this->redirectToRoute('repurpose_studio', ['id' => $post->id]);
    }
}
