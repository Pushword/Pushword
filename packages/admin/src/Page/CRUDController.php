<?php

namespace Pushword\Admin\Page;

use Pushword\Core\Repository\Repository;
use Sonata\AdminBundle\Controller\CRUDController as SonataCRUDController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CRUDController extends SonataCRUDController implements CRUDControllerInterface
{
    protected $params;

    public function setParams(ParameterBagInterface $params)
    {
        $this->params = $params;
    }

    public function listAction(Request $request): Response
    {
        if ($listMode = $request->get('_list_mode')) {
            $this->admin->setListMode($listMode);
        }

        $listMode = $this->admin->getListMode();
        if ('tree' === $listMode) {
            return $this->treeAction();
        }

        return parent::listAction($request);
    }

    public function treeAction()
    {
        $pages = Repository::getPageRepository($this->getDoctrine(), $this->params->get('pw.entity_page'))
            ->getPagesWithoutParent();

        return $this->renderWithExtraParams('@pwAdmin/page/page_treeView.html.twig', [
            'pages' => $pages,
            'list' => $this->admin->getList(),
            'admin' => $this->admin,
            'base_template' => $this->getBaseTemplate(),
            'action' => 'list',
        ]);
    }
}
