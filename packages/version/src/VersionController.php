<?php

namespace Pushword\Version;

use Exception;
use Pushword\Core\Repository\Repository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;

class VersionController extends AbstractController
{
    private Versionner $versionner;
    private string $pageClass;

    /**
     * @required
     */
    public function setVersionner(Versionner $versionner)
    {
        $this->versionner = $versionner;
    }

    /**
     * @required
     */
    public function setParams(ParameterBagInterface $params)
    {
        $this->pageClass = $params->get('pw.entity_page');
    }

    /**
     * @Security("is_granted('ROLE_PUSHWORD_ADMIN')")
     */
    public function loadVersion(string $id, string $version): Response
    {
        $this->versionner->loadVersion($id, $version);

        return $this->redirectToRoute('admin_app_page_edit', ['id' => $id]);
    }

    public function resetVersionning(string $id): Response
    {
        $this->versionner->reset($id);

        return $this->redirectToRoute('admin_app_page_edit', ['id' => $id]);
    }

    public function listVersion(string $id): Response
    {
        $page = Repository::getPageRepository($this->get('doctrine'), $this->pageClass)->findOneBy(['id' => $id]);

        if (! $page) {
            throw new Exception('Page not found `'.$id.'`');
        }

        $versions = $this->versionner->getPageVersions($page);

        $pageVersions = [];
        $entity = $this->pageClass;
        foreach ($versions as $version) {
            $object = new $entity();
            $pageVersions[$version] = $this->versionner->populate($object, $page->getId(), $version);
        }

        return $this->render('@PushwordVersion/list.html.twig', [
            'page' => $page,
            'pages' => $pageVersions,
        ]);
    }
}
