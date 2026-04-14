<?php

namespace Pushword\Version;

use Doctrine\Persistence\ManagerRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use Exception;
use Pushword\Admin\Service\AdminUrlGeneratorAlias;
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

    private AdminUrlGeneratorAlias $adminUrlGenerator;

    private AdminContextProviderInterface $adminContextProvider;

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

    #[Required]
    public function setAdminUrlGenerator(AdminUrlGeneratorAlias $adminUrlGenerator): void
    {
        $this->adminUrlGenerator = $adminUrlGenerator;
    }

    #[Required]
    public function setAdminContextProvider(AdminContextProviderInterface $adminContextProvider): void
    {
        $this->adminContextProvider = $adminContextProvider;
    }

    #[AdminRoute(path: '/version/{id}/reset', name: 'version_reset')]
    #[IsGranted('ROLE_PUSHWORD_ADMIN')]
    public function resetVersioning(Request $request, int $id): RedirectResponse
    {
        $this->versionner->reset($id);

        $this->getFlashBagFromRequest($request)->add('success', $this->translator->trans('versionResetHistory'));

        return $this->redirect($this->adminUrlGenerator->generate('admin_page_edit', ['id' => $id]));
    }

    private function getFlashBagFromRequest(Request $request): FlashBagInterface
    {
        $session = $request->getSession();

        if ($session instanceof FlashBagAwareSessionInterface) {
            return $session->getFlashBag();
        }

        throw new Exception();
    }

    #[AdminRoute(path: '/version/{id}/list', name: 'version_list')]
    #[IsGranted('ROLE_PUSHWORD_ADMIN')]
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
            $pageVersions[$version] = $this->versionner->populate($object, $version, (int) $page->id);
        }

        return $this->renderAdmin('@PushwordVersion/list.html.twig', [
            'page' => $page,
            'pages' => $pageVersions,
        ]);
    }

    #[AdminRoute(path: '/version/{id}/{version}', name: 'version_load')]
    #[IsGranted('ROLE_PUSHWORD_ADMIN')]
    public function loadVersion(string $id, string $version): RedirectResponse
    {
        $this->versionner->loadVersion($id, $version);

        return $this->redirect($this->adminUrlGenerator->generate('admin_page_edit', ['id' => $id]));
    }

    #[AdminRoute(path: '/version/{id}/compare/{versionLeft}/{versionRight}', name: 'version_compare')]
    #[IsGranted('ROLE_PUSHWORD_ADMIN')]
    public function compareVersion(string $id, string $versionLeft, string $versionRight = 'current'): Response
    {
        $page = $this->doctrine->getRepository(Page::class)->findOneBy(['id' => $id]);

        if (! $page instanceof Page) {
            throw new Exception('Page not found `'.$id.'`');
        }

        $allVersions = $this->versionner->getPageVersions($page);

        // Left side (historical version to compare)
        $leftPage = new Page();
        $this->versionner->populate($leftPage, $versionLeft, (int) $page->id);

        // Right side (current page or another version)
        if ('current' === $versionRight) {
            $rightPage = $page;
        } else {
            $rightPage = new Page();
            $this->versionner->populate($rightPage, $versionRight, (int) $page->id);
        }

        return $this->renderAdmin('@PushwordVersion/compare.html.twig', [
            'page' => $page,
            'leftPage' => $leftPage,
            'rightPage' => $rightPage,
            'versionLeft' => $versionLeft,
            'versionRight' => $versionRight,
            'allVersions' => $allVersions,
        ]);
    }

    #[AdminRoute(path: '/version/{id}/save-compare', name: 'version_save_compare', options: ['methods' => ['POST']])]
    #[IsGranted('ROLE_PUSHWORD_ADMIN')]
    public function saveCompare(Request $request, int $id): RedirectResponse
    {
        $page = $this->doctrine->getRepository(Page::class)->findOneBy(['id' => $id]);

        if (! $page instanceof Page) {
            throw new Exception('Page not found `'.$id.'`');
        }

        $mainContent = $request->request->get('mainContent', '');
        $page->setMainContent(\is_string($mainContent) ? $mainContent : '');

        $h1 = $request->request->get('h1');
        if (\is_string($h1) && '' !== $h1) {
            $page->setH1($h1);
        }

        $title = $request->request->get('title');
        if (\is_string($title) && '' !== $title) {
            $page->setTitle($title);
        }

        $slug = $request->request->get('slug');
        if (\is_string($slug) && '' !== $slug) {
            $page->setSlug($slug);
        }

        $this->doctrine->getManager()->flush();

        $this->getFlashBagFromRequest($request)->add('success', $this->translator->trans('versionSaveSuccess'));

        return $this->redirect($this->adminUrlGenerator->generate('admin_page_edit', ['id' => $id]));
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function renderAdmin(string $view, array $parameters = []): Response
    {
        $parameters['ea'] = $this->adminContextProvider->getContext();

        return $this->render($view, $parameters);
    }
}
