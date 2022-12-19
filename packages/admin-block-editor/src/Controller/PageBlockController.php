<?php

namespace Pushword\AdminBlockEditor\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\AutowiringTrait\RequiredApps;
use Pushword\Core\AutowiringTrait\RequiredPageClass;
use Pushword\Core\Repository\Repository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment as Twig;

#[IsGranted('ROLE_EDITOR')]
final class PageBlockController extends AbstractController
{
    use RequiredApps;
    use RequiredPageClass;

    public function __construct(private readonly EntityManagerInterface $em, private readonly Twig $twig)
    {
    }

    public function manage(Request $request, string $id = '0'): Response
    {
        $id = (int) $id;
        $content = $request->toArray();

        $request->attributes->set('_route', 'pushword_page'); // 'custom_host_pushword_page'
        // TODO: sanitize

        if (0 !== $id) {
            $currentPage = Repository::getPageRepository($this->em, $this->pageClass)->findOneBy(['id' => $id]);
            if (! $currentPage instanceof \Pushword\Core\Entity\PageInterface) {
                throw new \Exception('Page not found');
            }

            $this->apps->switchCurrentApp($currentPage);
        }

        $htmlContent = $this->twig->render(
            $this->apps->getApp()->getView('/block/pages_list_preview.html.twig', '@PushwordAdminBlockEditor'),
            ['page' => $currentPage ?? null, 'block' => ['data' => $content]]
        );

        return new Response(\Safe\json_encode([
            'success' => 1,
            'content' => $htmlContent,
        ]));
    }
}
