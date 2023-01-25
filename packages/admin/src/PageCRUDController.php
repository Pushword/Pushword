<?php

namespace Pushword\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;
use Pushword\Core\Entity\PageInterface;
use Pushword\Core\Repository\Repository;
use Sonata\AdminBundle\Controller\CRUDController as SonataCRUDController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends SonataCRUDController<PageInterface>
 */
class PageCRUDController extends SonataCRUDController implements PageCRUDControllerInterface
{
    protected ParameterBagInterface $params;

    /**
     * https://github.com/symfony/symfony/blob/5.4/src/Symfony/Bundle/FrameworkBundle/Controller/AbstractController.php.
     */
    #[\Symfony\Contracts\Service\Attribute\Required]
    public EntityManagerInterface $entityManager;

    /**
     * https://github.com/symfony/symfony/blob/5.4/src/Symfony/Bundle/FrameworkBundle/Controller/AbstractController.php.
     */
    #[\Symfony\Contracts\Service\Attribute\Required]
    public function loadContainer(ContainerInterface $container): void
    {
        $this->container = $container;

        if (! $this->container->has('parameter_bag')) { // @phpstan-ignore-line
            throw new \Exception('patch no longer worked');
        }
    }

    #[\Symfony\Contracts\Service\Attribute\Required]
    public function setParams(ParameterBagInterface $parameterBag): void
    {
        $this->params = $parameterBag;
    }

    public function listAction(Request $request): Response
    {
        if (($listMode = $request->query->get('_list_mode')) !== null) {
            $this->admin->setListMode($listMode);
        }

        $listMode = $this->admin->getListMode();
        if ('tree' === $listMode) {
            parent::listAction($request);

            return $this->treeAction();
        }

        return parent::listAction($request);
    }

    public function treeAction(): Response
    {
        $pages = Repository::getPageRepository($this->entityManager, $this->params->get('pw.entity_page')) // @phpstan-ignore-line
            ->getPagesWithoutParent();

        return $this->renderWithExtraParams('@pwAdmin/page/page_treeView.html.twig', [
            'pages' => $pages,
            'list' => $this->admin->getList(),
            'admin' => $this->admin,
            'base_template' => $this->getBaseTemplate(),
            'action' => 'list',
        ]);
    }

    protected function redirectTo(Request $request, object $object): RedirectResponse
    {
        if (null !== $request->request->get('btn_update_and_list')) {
            return new RedirectResponse($this->admin->generateObjectUrl('show', $object));
        }

        if (null !== $request->request->get('btn_create_and_list')) {
            return new RedirectResponse($this->admin->generateObjectUrl('show', $object));
        }

        return parent::redirectTo($request, $object);
    }
}
