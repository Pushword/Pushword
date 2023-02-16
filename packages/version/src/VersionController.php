<?php

namespace Pushword\Version;

use Doctrine\Persistence\ManagerRegistry;
use Pushword\Core\Entity\PageInterface;
use Pushword\Core\Repository\Repository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

class VersionController extends AbstractController
{
    private Versionner $versionner;

    private TranslatorInterface $translator;

    /**
     * @var class-string<PageInterface>
     */
    private string $pageClass;

    private ManagerRegistry $doctrine;

    #[\Symfony\Contracts\Service\Attribute\Required]
    public function setDoctrine(ManagerRegistry $doctrine): void
    {
        $this->doctrine = $doctrine;
    }

    #[\Symfony\Contracts\Service\Attribute\Required]
    public function setVersionner(Versionner $versionner): void
    {
        $this->versionner = $versionner;
    }

    #[\Symfony\Contracts\Service\Attribute\Required]
    public function setTranslator(TranslatorInterface $translator): void
    {
        $this->translator = $translator;
    }

    /**
     * @psalm-suppress PossiblyInvalidArgument
     * @psalm-suppress InvalidPropertyAssignmentValue
     */
    #[\Symfony\Contracts\Service\Attribute\Required]
    public function setParams(ParameterBagInterface $parameterBag): void
    {
        $this->pageClass = $parameterBag->get('pw.entity_page'); // @phpstan-ignore-line
    }

    #[IsGranted('ROLE_PUSHWORD_ADMIN')]
    public function loadVersion(string $id, string $version): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $this->versionner->loadVersion($id, $version);

        return $this->redirectToRoute('admin_app_page_edit', ['id' => $id]);
    }

    /** @psalm-suppress  UndefinedInterfaceMethod */
    public function resetVersioning(Request $request, int $id): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $this->versionner->reset($id);
        $request->getSession()->getFlashBag()->add('success', $this->translator->trans('version.reset_history')); // @phpstan-ignore-line

        return $this->redirectToRoute('admin_app_page_edit', ['id' => $id]);
    }

    public function listVersion(string $id): Response
    {
        $page = Repository::getPageRepository($this->doctrine, $this->pageClass)->findOneBy(['id' => $id]);

        if (! $page instanceof \Pushword\Core\Entity\PageInterface) {
            throw new \Exception('Page not found `'.$id.'`');
        }

        $versions = $this->versionner->getPageVersions($page);

        $pageVersions = [];
        $entity = $this->pageClass;
        foreach ($versions as $version) {
            $object = new $entity();
            $pageVersions[$version] = $this->versionner->populate($object, $version, (int) $page->getId());
        }

        return $this->render('@PushwordVersion/list.html.twig', [
            'page' => $page,
            'pages' => $pageVersions,
        ]);
    }
}
