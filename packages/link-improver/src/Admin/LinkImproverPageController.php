<?php

namespace Pushword\LinkImprover\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use Pushword\Core\Content\ContentPipelineFactory;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Site\SiteRegistry;
use Pushword\LinkImprover\AddedLinksRegistry;
use Pushword\LinkImprover\InternalLinkSources;
use Pushword\LinkImprover\LinkImprover;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The per-page half of the audit surface: what the improver inserted into this
 * one page, and the numbers that decided it.
 *
 * It renders the page on request rather than reading a stored report, because
 * only a render knows what survived the cap — and one page is cheap. The
 * whole-site view stays in `pw:link-improver`, which is the same data over
 * every page.
 *
 * Inbound ("which pages link here") is deliberately absent: it would mean
 * rendering the rest of the host on an admin page load, and page-scanner's
 * link graph already reports inbound counts, auto links included, from a scan
 * that renders everything once.
 */
#[IsGranted('ROLE_PUSHWORD_ADMIN')]
final class LinkImproverPageController extends AbstractController
{
    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly ContentPipelineFactory $pipelineFactory,
        private readonly AddedLinksRegistry $addedLinks,
        private readonly SiteRegistry $apps,
        private readonly AdminContextProviderInterface $adminContextProvider,
    ) {
    }

    #[AdminRoute(path: '/link-improver/page/{id}', name: 'link_improver_page')]
    public function __invoke(int $id): Response
    {
        $page = $this->pageRepository->find($id);
        if (! $page instanceof Page) {
            throw new NotFoundHttpException();
        }

        $site = $this->apps->get($page->host);
        $enabled = true === $site->get('link_improver');
        $ignored = \in_array(InternalLinkSources::url($page->slug), $site->getStringList('link_improver_ignored_urls'), true);

        if ($enabled) {
            // Report what rendering produces NOW, not what an earlier render in
            // this request left memoized.
            $this->pipelineFactory->reset();
            $this->addedLinks->reset();
            $this->pipelineFactory->get($page)->getMainContent();
        }

        return $this->render('@PushwordLinkImprover/page.html.twig', [
            'ea' => $this->adminContextProvider->getContext(),
            'page' => $page,
            'enabled' => $enabled,
            'ignored' => $ignored,
            'links' => $enabled ? $this->addedLinks->forPage($page) : [],
            'stats' => $enabled ? $this->addedLinks->statsForPage($page) : null,
            // Resolved the way the filter resolves it, so the panel names the cap
            // the render used rather than the one the app spelled out.
            'ratio' => LinkImprover::maxLinks($site->get('link_improver_max_links')) < 1,
            'keywords' => InternalLinkSources::keywords($page->name),
        ]);
    }
}
