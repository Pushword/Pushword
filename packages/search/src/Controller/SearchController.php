<?php

namespace Pushword\Search\Controller;

use Pushword\Core\Controller\AbstractPushwordController;
use Pushword\Core\Controller\RoutePatterns;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Site\RequestContext;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Search\Service\Searcher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment as Twig;

final class SearchController extends AbstractPushwordController
{
    public function __construct(
        SiteRegistry $apps,
        RequestContext $requestContext,
        Twig $twig,
        private readonly Searcher $searcher,
        private readonly PageRepository $pageRepository,
    ) {
        parent::__construct($apps, $requestContext, $twig);
    }

    #[Route('/{host}/{_locale}search', name: 'custom_host_pushword_search', requirements: ['_locale' => RoutePatterns::LOCALE, 'host' => RoutePatterns::HOST], methods: ['GET', 'HEAD'], priority: -30)]
    #[Route('/{_locale}search', name: 'pushword_search', requirements: ['_locale' => RoutePatterns::LOCALE], methods: ['GET', 'HEAD'], priority: -31)]
    public function search(Request $request): Response
    {
        $host = $this->apps->switchSite($request->attributes->getString('host', $request->getHost()))->get()->getMainHost();
        $locale = '' !== rtrim($request->getLocale(), '/') ? rtrim($request->getLocale(), '/') : null;

        $query = trim($request->query->getString('q'));
        $page = max(1, $request->query->getInt('page', 1));

        $results = $this->searcher->search($host, $query, $page, locale: $locale);

        if ('json' === $request->getRequestFormat() || 'json' === $request->query->get('format')) {
            return new JsonResponse($results);
        }

        $chrome = $this->pageRepository->getPage('homepage', $host)
            ?? throw $this->createNotFoundException('No homepage found to render the search page.');

        return $this->render('@PushwordSearch/search.html.twig', [
            'page' => $chrome,
            'query' => $query,
            'results' => $results,
            ...$this->apps->getApp()->getParamsForRendering(),
        ]);
    }
}
