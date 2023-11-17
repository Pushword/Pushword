<?php

namespace Pushword\Admin\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Entity\PageInterface;
use Pushword\Core\Repository\Repository;
use Sonata\AdminBundle\Controller\CRUDController as SonataCRUDController;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * @extends SonataCRUDController<PageInterface>
 */
#[AutoconfigureTag('controller.service_arguments')]
class PageCRUDController extends SonataCRUDController
{
    protected ParameterBagInterface $params;

    #[Required]
    public EntityManagerInterface $entityManager;

    #[Required]
    public function setParams(ParameterBagInterface $parameterBag): void
    {
        $this->params = $parameterBag;
    }

    public function list(Request $request): Response
    {
        if (($listMode = $request->query->get('_list_mode')) !== null) {
            $this->admin->setListMode($listMode);
        }

        $listMode = $this->admin->getListMode();
        if ('tree' === $listMode) {
            parent::listAction($request);

            return $this->tree();
        }

        return parent::listAction($request);
    }

    public function tree(): Response
    {
        $pages = Repository::getPageRepository($this->entityManager, $this->params->get('pw.entity_page')) // @phpstan-ignore-line
            ->getPagesWithoutParent();

        return $this->render('@pwAdmin/page/page_treeView.html.twig', [
            'pages' => $pages,
            'list' => $this->admin->getList(),
            'admin' => $this->admin,
            // 'base_template' => $this->getBaseTemplate(),
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
