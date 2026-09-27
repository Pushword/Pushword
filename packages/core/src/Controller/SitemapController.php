<?php

declare(strict_types=1);

namespace Pushword\Core\Controller;

use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Site\RequestContext;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment as Twig;

/**
 * Handles sitemap generation in XML and TXT formats.
 */
final class SitemapController extends AbstractPushwordController
{
    public function __construct(
        SiteRegistry $apps,
        RequestContext $requestContext,
        Twig $twig,
        private readonly PageRepository $pageRepository,
    ) {
        parent::__construct($apps, $requestContext, $twig);
    }

    #[Route('/{_locale}sitemap.{_format}', name: 'pushword_page_sitemap', requirements: ['_locale' => RoutePatterns::LOCALE, '_format' => 'xml|txt'], methods: ['GET', 'HEAD'], priority: -10)]
    #[Route('/{host}/{_locale}sitemap.{_format}', name: 'custom_host_pushword_page_sitemap', requirements: ['_locale' => RoutePatterns::LOCALE, '_format' => 'xml|txt', 'host' => RoutePatterns::HOST], methods: ['GET', 'HEAD'], priority: -11)]
    public function show(Request $request, string $_format): Response
    {
        $pages = $this->getPages($request);

        if ([] === $pages) {
            throw $this->createNotFoundException();
        }

        return $this->render(
            $this->getView('/page/sitemap.'.$_format.'.twig'),
            [
                'pages' => $pages,
                'app_base_url' => $this->apps->getApp()->baseUrl,
            ]
        );
    }

    /**
     * @return list<Page>
     */
    private function getPages(Request $request, ?int $limit = null): array
    {
        $requestedLocale = rtrim($request->attributes->getString('_locale'), '/');

        /** @var list<Page> $pages */
        $pages = $this->pageRepository->getIndexablePagesQuery(
            $this->apps->getMainHost(),
            '' !== $requestedLocale ? $requestedLocale : $this->requestContext->currentSite->locale,
            $limit
        )
        ->orderBy('p.publishedAt', 'DESC')
        ->getQuery()->getResult();

        // sitemap.xml.twig reads every page's translations (hreflang).
        $this->pageRepository->preloadTranslations($pages);

        return $pages;
    }
}
