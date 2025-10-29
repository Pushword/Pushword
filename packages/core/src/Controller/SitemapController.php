<?php

namespace Pushword\Core\Controller;

use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Repository\PageRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment as Twig;

/**
 * Handles sitemap generation in XML and TXT formats.
 */
final class SitemapController extends AbstractPushwordController
{
    public function __construct(
        RequestStack $requestStack,
        AppPool $apps,
        Twig $twig,
        private readonly PageRepository $pageRepository,
    ) {
        parent::__construct($requestStack, $apps, $twig);
    }

    #[Route(
        '/{_locale}sitemap.{_format}',
        name: 'pushword_page_sitemap',
        methods: ['GET', 'HEAD'],
        requirements: ['_locale' => RoutePatterns::LOCALE, '_format' => 'xml|txt'],
        priority: -10
    )]
    #[Route(
        '/{host}/{_locale}sitemap.{_format}',
        name: 'custom_host_pushword_page_sitemap',
        methods: ['GET', 'HEAD'],
        requirements: ['_locale' => RoutePatterns::LOCALE, '_format' => 'xml|txt', 'host' => RoutePatterns::HOST],
        priority: -11
    )]
    public function show(Request $request, string $_format): Response
    {
        $this->initHost($request);

        $pages = $this->getPages($request, null);

        if (! \is_array($pages) || ! isset($pages[0])) {
            throw $this->createNotFoundException();
        }

        return $this->render(
            $this->getView('/page/sitemap.'.$_format.'.twig'),
            [
                'pages' => $pages,
                'app_base_url' => $this->apps->getApp()->getBaseUrl(),
            ]
        );
    }

    /**
     * @return mixed //array<Page>
     */
    private function getPages(Request $request, ?int $limit = null): mixed
    {
        $requestedLocale = rtrim($request->getLocale(), '/');

        return $this->pageRepository->getIndexablePagesQuery(
            (string) $this->apps->getMainHost(),
            '' !== $requestedLocale ? $requestedLocale : $this->apps->getApp()->getLocale(),
            $limit
        )
        ->orderBy('p.publishedAt', 'DESC')
        ->getQuery()->getResult();
    }
}
