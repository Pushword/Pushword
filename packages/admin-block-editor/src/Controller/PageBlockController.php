<?php

namespace Pushword\AdminBlockEditor\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Pushword\Core\AutowiringTrait\RequiredApps;
use Pushword\Core\AutowiringTrait\RequiredPageClass;
use Pushword\Core\Repository\Repository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment as Twig;

/**
 * @IsGranted("ROLE_EDITOR")
 */
final class PageBlockController extends AbstractController
{
    use RequiredApps;
    use RequiredPageClass;

    private EntityManagerInterface $em;

    private Twig $twig;

    public function __construct(EntityManagerInterface $entityManager, Twig $twig)
    {
        $this->em = $entityManager;
        $this->twig = $twig;
    }

    /**
     * @param int|string $id
     */
    public function manage(Request $request, $id = ''): Response
    {
        $content = $request->toArray();

        $request->attributes->set('_route', 'pushword_page'); //'custom_host_pushword_page'
        // TODO: sanitize

        if ('' !== $id) {
            $currentPage = Repository::getPageRepository($this->em, $this->pageClass)->findOneBy(['id' => $id]);
            if (null === $currentPage) {
                throw new Exception('Page not found');
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
