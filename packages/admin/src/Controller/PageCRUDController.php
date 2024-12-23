<?php

namespace Pushword\Admin\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Pushword\Core\Entity\Page;
use Sonata\AdminBundle\Controller\CRUDController as SonataCRUDController;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * @extends SonataCRUDController<Page>
 */
#[AutoconfigureTag('controller.service_arguments')]
class PageCRUDController extends SonataCRUDController
{
    #[Required]
    public ParameterBagInterface $params;

    #[Required]
    public EntityManagerInterface $entityManager;

    public function list(Request $request): Response
    {
        if (($listMode = $request->query->getString('_list_mode')) !== '') {
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
        $pages = $this->entityManager->getRepository(Page::class)
            ->getPagesWithoutParent();

        return $this->render('@pwAdmin/page/page_treeView.html.twig', [
            'pages' => $pages,
            'list' => $this->admin->getList(),
            'admin' => $this->admin,
            // 'base_template' => $this->getBaseTemplate(),
            'action' => 'list',
        ]);
    }

    #[Override]
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
