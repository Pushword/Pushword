<?php

namespace Pushword\Admin\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[AutoconfigureTag('controller.service_arguments')]
class MarkdownCheatsheetController extends AbstractController
{
    #[Route('/admin/dashboard', name: 'pushword_admin_dashboard')]
    public function redirectDashboard(): Response
    {
        return $this->redirectToRoute('admin_page_list');
    }

    #[Route('/admin/markdown-cheatsheet', name: 'pushword_markdown_cheatsheet', methods: ['GET', 'HEAD'])]
    public function markdownCheatsheet(): Response
    {
        return $this->render('@pwAdmin/markdown_cheatsheet.html.twig');
    }
}
