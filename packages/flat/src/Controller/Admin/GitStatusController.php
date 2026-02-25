<?php

declare(strict_types=1);

namespace Pushword\Flat\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use Pushword\Flat\Service\GitAutoCommitter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Service\Attribute\Required;

#[IsGranted('ROLE_PUSHWORD_ADMIN')]
final class GitStatusController extends AbstractController
{
    private AdminContextProviderInterface $adminContextProvider;

    public function __construct(
        private readonly GitAutoCommitter $gitAutoCommitter,
    ) {
    }

    #[Required]
    public function setAdminContextProvider(AdminContextProviderInterface $adminContextProvider): void
    {
        $this->adminContextProvider = $adminContextProvider;
    }

    #[AdminRoute(
        path: '/git-status',
        name: 'git_status',
    )]
    public function index(): Response
    {
        return $this->render('@PushwordFlat/admin/git_status.html.twig', [
            'ea' => $this->adminContextProvider->getContext(),
            'enabled' => $this->gitAutoCommitter->isEnabled(),
            'history' => $this->gitAutoCommitter->getHistory(),
        ]);
    }
}
