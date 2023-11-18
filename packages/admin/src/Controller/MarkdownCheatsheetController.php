<?php

namespace Pushword\Admin\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MarkdownCheatsheetController extends AbstractController
{
    #[Route('/admin/dashboard', name: 'pushword_admin_dashboard')]
    public function redirectDashboard(string $url, int $status = 302): Response
    {
        return $this->redirectToRoute('admin_page_list');
    }

    #[Route('/admin/markdown-cheatsheet', name: 'pushword_markdown_cheatsheet', methods: ['GET', 'HEAD'])]
    public function markdownCheatsheet(): Response
    {
        return $this->render('@pwAdmin/markdown_cheatsheet.html.twig');
    }
}
