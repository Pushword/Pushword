<?php

namespace Pushword\Version;

use Doctrine\Persistence\ManagerRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use Exception;
use LogicException;
use Pushword\Admin\Service\AdminUrlGeneratorAlias;
use Pushword\Core\Entity\Page;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Attribute\Route;
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
    #[Route(path: '/{id}/reset', name: 'pushword_version_reset', methods: ['GET'], priority: -20)]
    #[IsGranted('ROLE_PUSHWORD_ADMIN')]
    public function resetVersioning(Request $request, int $id): RedirectResponse
    {
        $this->versionner->reset($id);

        $this->getFlashBagFromRequest($request)->add('success', $this->translator->trans('version.reset_history'));

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
    #[Route(path: '/{id}/list', name: 'pushword_version_list', methods: ['GET'], priority: -20)]
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
            $pageVersions[$version] = $this->versionner->populate($object, $version, (int) $page->getId());
        }

        return $this->renderAdmin('@PushwordVersion/list.html.twig', [
            'page' => $page,
            'pages' => $pageVersions,
        ]);
    }

    #[AdminRoute(path: '/version/{id}/{version}', name: 'version_load')]
    #[Route(path: '/{id}/{version}', name: 'pushword_version_load', methods: ['GET'], priority: -20)]
    #[IsGranted('ROLE_PUSHWORD_ADMIN')]
    public function loadVersion(string $id, string $version): RedirectResponse
    {
        $this->versionner->loadVersion($id, $version);

        return $this->redirect($this->adminUrlGenerator->generate('admin_page_edit', ['id' => $id]));
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function renderAdmin(string $view, array $parameters = []): Response
    {
        if (! isset($this->adminContextProvider)) {
            throw new LogicException('Admin context provider must be injected before rendering.');
        }

        $context = $this->adminContextProvider->getContext();
        if (null === $context) {
            throw new LogicException('EasyAdmin context is not available. Please use the admin routes to access this page.');
        }

        $parameters['ea'] = $context;

        return $this->render($view, $parameters);
    }
}
