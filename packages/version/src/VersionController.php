<?php

namespace Pushword\Version;

use Exception;
use Pushword\Core\Entity\PageInterface;
use Pushword\Core\Repository\Repository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;

class VersionController extends AbstractController
{
    /** @var Versionner */
    private $versionner;
    /** @var string */
    private $pageClass;

    /** @required */
    public function setVersionner(Versionner $versionner): void
    {
        $this->versionner = $versionner;
    }

    /** @required */
    public function setParams(ParameterBagInterface $params): void
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

    public function resetVersioning(string $id): Response
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
            /**
             * @var PageInterface $object
             * @psalm-suppress InvalidStringClass
             */
            $object = new $entity();
            $pageVersions[$version] = $this->versionner->populate($object, $page->getId(), $version);
        }

        return $this->render('@PushwordVersion/list.html.twig', [
            'page' => $page,
            'pages' => $pageVersions,
        ]);
    }
}
