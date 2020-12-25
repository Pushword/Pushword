<?php

namespace Pushword\Version;

use Doctrine\Persistence\ManagerRegistry;
use Exception;
use Pushword\Core\Entity\Page;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AutoconfigureTag('controller.service_arguments')]
class VersionController extends AbstractController
{
    private Versionner $versionner;

    private TranslatorInterface $translator;

    private ManagerRegistry $doctrine;

    #[Required]
    public function setDoctrine(ManagerRegistry $doctrine): void
    {
        $this->doctrine = $doctrine;
    }

    #[Required]
    public function setVersionner(Versionner $versionner): void
    {
        $this->versionner = $versionner;
    }

    #[Required]
    public function setTranslator(TranslatorInterface $translator): void
    {
        $this->translator = $translator;
    }

    #[IsGranted('ROLE_PUSHWORD_ADMIN')]
    public function loadVersion(string $id, string $version): RedirectResponse
    {
        $this->versionner->loadVersion($id, $version);

        return $this->redirectToRoute('admin_page_edit', ['id' => $id]);
    }

    private function getFlashBagFromRequest(Request $request): FlashBagInterface
    {
        $session = $request->getSession();

        if ($session instanceof FlashBagAwareSessionInterface) {
            return $session->getFlashBag();
        }

        throw new Exception();
    }

    public function resetVersioning(Request $request, int $id): RedirectResponse
    {
        $this->versionner->reset($id);

        $this->getFlashBagFromRequest($request)->add('success', $this->translator->trans('version.reset_history'));

        return $this->redirectToRoute('admin_page_edit', ['id' => $id]);
    }

    public function listVersion(string $id): Response
    {
        $page = $this->doctrine->getRepository(Page::class)->findOneBy(['id' => $id]);

        if (! $page instanceof Page) {
            throw new Exception('Page not found `'.$id.'`');
        }

        $versions = $this->versionner->getPageVersions($page);

        $pageVersions = [];
        foreach ($versions as $version) {
            $object = new Page();
            $pageVersions[$version] = $this->versionner->populate($object, $version, (int) $page->getId());
        }

        return $this->render('@PushwordVersion/list.html.twig', [
            'page' => $page,
            'pages' => $pageVersions,
        ]);
    }
}
