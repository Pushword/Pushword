<?php

namespace Pushword\AdminBlockEditor\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Page;

use function Safe\json_encode;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment as Twig;

#[IsGranted('ROLE_EDITOR')]
final class PageBlockController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Twig $twig,
        private readonly AppPool $apps,
    ) {
    }

    #[Route('/admin/page/block/{id}', name: 'admin_page_block', methods: ['POST'], defaults: ['id' => '0'], requirements: ['id' => '\d*'])]
    public function manage(Request $request, string $id = '0'): Response
    {
        $id = (int) $id;
        $content = $request->toArray();

        $request->attributes->set('_route', 'pushword_page'); // 'custom_host_pushword_page'
        // TODO: sanitize

        if (0 !== $id) {
            $currentPage = $this->em->getRepository(Page::class)->findOneBy(['id' => $id]);
            if (! $currentPage instanceof Page) {
                throw new Exception('Page not found');
            }

            $this->apps->switchCurrentApp($currentPage);
        }

        $htmlContent = $this->twig->render(
            $this->apps->getApp()->getView('/block/pages_list_preview.html.twig', '@PushwordAdminBlockEditor'),
            ['page' => $currentPage ?? null, 'block' => ['data' => $content]]
        );

        return new Response(json_encode([
            'success' => 1,
            'content' => $htmlContent,
        ]));
    }
}
