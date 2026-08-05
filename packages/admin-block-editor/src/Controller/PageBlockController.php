<?php

namespace Pushword\AdminBlockEditor\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Pushword\Core\Entity\Page;
use Pushword\Core\Site\SiteRegistry;

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
        private readonly SiteRegistry $apps,
    ) {
    }

    #[Route('/admin/page/block/{id}', name: 'admin_page_block', requirements: ['id' => '\d*'], defaults: ['id' => '0'], methods: ['POST'])]
    public function manage(Request $request, string $id = '0'): Response
    {
        $id = (int) $id;
        $content = $request->toArray();
        $content['display'] = $this->displayName($content['display'] ?? '');

        $request->attributes->set('_route', 'pushword_page'); // 'custom_host_pushword_page'

        if (0 !== $id) {
            $currentPage = $this->em->getRepository(Page::class)->findOneBy(['id' => $id]);
            if (! $currentPage instanceof Page) {
                throw new Exception('Page not found');
            }

            $this->apps->switchSite($currentPage);
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

    /**
     * The editor posts `display` verbatim and pages_list() turns a bare name into a
     * view (`/component/pages_list_<name>.html.twig`) — so anything that could read as
     * a template path instead falls back to the built-in list.
     */
    private function displayName(mixed $display): string
    {
        return \is_string($display) && 1 === preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $display)
            ? $display : 'list';
    }
}
