<?php

namespace Pushword\Admin\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Override;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;
use Twig\Attribute\AsTwigFunction;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly AdminMenu $adminMenu,
    ) {
    }

    #[Override]
    public function index(): Response
    {
        $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);

        return $this->redirect($adminUrlGenerator->setController(PageCrudController::class)->generateUrl());
    }

    #[Override]
    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Pushword')
            ->setFaviconPath('/bundles/pushwordcore/favicon.ico')
            // ->setLocales(['en', 'fr']) - use User's Locale instead
            ->disableDarkMode();
    }

    #[Override]
    public function configureCrud(): Crud
    {
        return Crud::new()
            ->overrideTemplates([
                'crud/index' => '@pwAdmin/crud/index.html.twig',
            ]);
    }

    #[Override]
    public function configureAssets(): Assets
    {
        return Assets::new()
            ->addCssFile($this->versionedAsset('/bundles/pushwordadmin/admin.css'))
            ->addJsFile($this->versionedAsset('/bundles/pushwordadmin/admin.js'))
            ->addJsFile($this->versionedAsset('/bundles/pushwordadminblockeditor/admin-block-editor.js'))
            ->addCssFile($this->versionedAsset('/bundles/pushwordadminblockeditor/style.css'));
    }

    #[Override]
    public function configureMenuItems(): iterable
    {
        yield from $this->adminMenu->configureMenuItems();
    }

    #[Override]
    public function configureUserMenu(UserInterface $user): UserMenu
    {
        return parent::configureUserMenu($user)
            ->displayUserName(false);
    }

    #[AsTwigFunction('versionedAsset')]
    private function versionedAsset(string $assetPath): string
    {
        /** @var string $projectDir */
        $projectDir = $this->getParameter('kernel.project_dir');
        $absolutePath = $projectDir.'/public'.$assetPath;
        $version = \is_file($absolutePath) ? (string) \filemtime($absolutePath) : (string) \time();

        return sprintf('%s?v=%s', $assetPath, $version);
    }
}
