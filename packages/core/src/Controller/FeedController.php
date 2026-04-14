<?php

namespace Pushword\Core\Controller;

use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Site\RequestContext;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment as Twig;

final class FeedController extends AbstractPushwordController
{
    public function __construct(
        SiteRegistry $apps,
        RequestContext $requestContext,
        Twig $twig,
        private readonly ParameterBagInterface $params,
        private readonly PageRepository $pageRepository,
        private readonly PageResolver $pageResolver,
    ) {
        parent::__construct($apps, $requestContext, $twig);
    }

    #[Route('/{_locale}feed.xml', name: 'pushword_page_main_feed', requirements: ['_locale' => RoutePatterns::LOCALE], methods: ['GET', 'HEAD'], priority: -20)]
    #[Route('/{host}/{_locale}feed.xml', name: 'custom_host_pushword_page_main_feed', requirements: ['_locale' => RoutePatterns::LOCALE, 'host' => RoutePatterns::HOST], methods: ['GET', 'HEAD'], priority: -21)]
    public function showMain(Request $request): Response
    {
        $locale = '' !== $request->getLocale() ? rtrim($request->getLocale(), '/') : $this->apps->getApp()->getDefaultLocale();
        $localeHomepage = $this->pageResolver->findPageOr404($request, $locale);
        $page = $localeHomepage ?? $this->pageResolver->findPageOr404($request, 'homepage');
        if (! $page instanceof Page) {
            throw $this->createNotFoundException('The page `homepage` was not found');
        }

        $request->setLocale($page->locale);

        $params = [
            'pages' => $this->getPages($request, 5),
            'page' => $page,
            'feedUri' => ($this->params->get('kernel.default_locale') === $locale ? '' : $locale.'/').'feed.xml',
        ];

        return $this->render(
            $this->getView('/page/rss.xml.twig'),
            [...$params, ...$this->apps->getApp()->getParamsForRendering()]
        );
    }

    #[Route('/{host}/{slug}.xml', name: 'custom_host_pushword_page_feed', requirements: ['slug' => RoutePatterns::SLUG, 'host' => RoutePatterns::HOST], methods: ['GET', 'HEAD'], priority: -40)]
    #[Route('/{slug}.xml', name: 'pushword_page_feed', requirements: ['slug' => RoutePatterns::SLUG], methods: ['GET', 'HEAD'], priority: -50)]
    public function show(Request $request, string $slug = ''): Response
    {
        if ('homepage' === $slug) {
            return $this->redirectToRoute('pushword_page_feed', ['slug' => 'index'], Response::HTTP_MOVED_PERMANENTLY);
        }

        $page = $this->pageResolver->findPageOr404($request, '' === $slug ? 'homepage' : $slug)
            ?? throw $this->createNotFoundException();

        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');

        if (! $page->hasChildrenPages()) {
            throw $this->createNotFoundException();
        }

        $request->setLocale($page->locale);

        return $this->render(
            $this->getView('/page/rss.xml.twig'),
            ['page' => $page, ...$this->apps->getApp()->getParamsForRendering()],
            $response
        );
    }

    /**
     * @return list<Page>
     */
    private function getPages(Request $request, ?int $limit = null): array
    {
        $requestedLocale = rtrim($request->getLocale(), '/');

        /** @var list<Page> */
        return $this->pageRepository->getIndexablePagesQuery(
            (string) $this->apps->getMainHost(),
            '' !== $requestedLocale ? $requestedLocale : $this->params->get('kernel.default_locale'),
            $limit
        )
        ->orderBy('p.publishedAt', 'DESC')
        ->getQuery()->getResult();
    }
}
