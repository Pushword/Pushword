<?php

namespace Pushword\Core\Controller;

use LogicException;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Router\PushwordRouteGenerator;
use Pushword\Core\Site\RequestContext;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Bundle\FrameworkBundle\Translation\Translator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Translation\DataCollectorTranslator;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as Twig;

final class PageController extends AbstractPushwordController
{
    /** @var DataCollectorTranslator|Translator */
    private readonly TranslatorInterface $translator;

    public function __construct(
        SiteRegistry $apps,
        RequestContext $requestContext,
        Twig $twig,
        private readonly PageResolver $pageResolver,
        TranslatorInterface $translator,
        private readonly PushwordRouteGenerator $routeGenerator,
        private readonly PageRepository $pageRepository,
    ) {
        if (! $translator instanceof DataCollectorTranslator && ! $translator instanceof Translator) {
            throw new LogicException('A symfony codebase changed make this hack impossible (cf setLocale). Get `'.$translator::class.'`');
        }

        parent::__construct($apps, $requestContext, $twig);

        $this->translator = $translator;
    }

    public function setHost(string $host): self
    {
        $this->requestContext->switchSite($host);

        return $this;
    }

    #[Route('/{host}/{slug}', name: 'custom_host_pushword_page', requirements: ['slug' => RoutePatterns::SLUG, 'host' => RoutePatterns::HOST], defaults: ['slug' => ''], methods: ['GET', 'HEAD', 'POST'], priority: -60)]
    #[Route('/{slug}', name: 'pushword_page', requirements: ['slug' => RoutePatterns::SLUG], methods: ['GET', 'HEAD', 'POST'], priority: -70)]
    #[Route('/{host}/{pager}', name: 'custom_host_pushword_page_homepage_pager', requirements: ['host' => RoutePatterns::HOST, 'pager' => RoutePatterns::PAGER_OPTIONAL], defaults: ['pager' => 1], methods: ['GET', 'HEAD', 'POST'], priority: -80)]
    #[Route('/{pager}', name: 'pushword_page_homepage_pager', requirements: ['pager' => RoutePatterns::PAGER], defaults: ['slug' => '', 'pager' => 1], methods: ['GET', 'HEAD', 'POST'], priority: -80)]
    #[Route('/{host}/{slug}/{pager}', name: 'custom_host_pushword_page_pager', requirements: [
        'slug' => RoutePatterns::SLUG,
        'host' => RoutePatterns::HOST,
        'pager' => RoutePatterns::PAGER,
    ], defaults: ['slug' => '', 'pager' => 1], methods: ['GET', 'HEAD', 'POST'], priority: -80)]
    #[Route('/{slug}/{pager}', name: 'pushword_page_pager', requirements: ['slug' => RoutePatterns::SLUG_WITH_TRAILING, 'pager' => RoutePatterns::PAGER], defaults: ['slug' => '', 'pager' => 1], methods: ['GET', 'HEAD', 'POST'], priority: -80)]
    public function show(Request $request, string $slug = ''): Response
    {
        $normalizedSlug = '' === $slug ? 'homepage' : $slug;
        $page = $this->pageResolver->findPageOr404($request, $normalizedSlug, true);

        if (null === $page) {
            $redirect = $this->resolveRedirectFrom($normalizedSlug);
            if (null !== $redirect) {
                return $this->redirect($redirect['url'], $redirect['code']);
            }

            throw $this->createNotFoundException();
        }

        $host = $request->query->get('host');
        if ('' !== $host && $host === $request->getHost()) {
            $redirect = $this->checkIfUriIsCanonical($request, $page);
            if (false !== $redirect) {
                return $this->redirect($redirect, Response::HTTP_MOVED_PERMANENTLY);
            }
        }

        if ($page->hasRedirection()) {
            return $this->redirect($this->resolveRedirectionUrl($page), $page->getRedirectionCode());
        }

        $request->setLocale($page->locale);
        $this->translator->setLocale($page->locale);

        return $this->showPage($page);
    }

    public function showPage(Page $page): Response
    {
        $this->requestContext->setCurrentPage($page);

        $params = ['page' => $page, ...$this->requestContext->getCurrentSite()->getParamsForRendering()];

        $view = $this->getView($page->getTemplate() ?? '/page/page.html.twig');

        $response = new Response();

        return $this->render($view, $params, $response);
    }

    /**
     * Serve a `redirectFrom` entry: when no page owns the requested slug, an old path
     * declared on a destination page's redirectFrom resolves here. Internal targets are
     * route-generated so the canonical (host-prefixed) URL is used.
     *
     * @return ?array{url: string, code: int}
     */
    private function resolveRedirectFrom(string $slug): ?array
    {
        $host = $this->requestContext->getCurrentSite()->getMainHost();
        $redirect = $this->pageRepository->getRedirectFor(Page::normalizeSlug($slug), $host);

        if (null === $redirect) {
            return null;
        }

        return ['url' => $this->generateRedirectTarget($redirect['url'], $host), 'code' => $redirect['code']];
    }

    private function resolveRedirectionUrl(Page $page): string
    {
        return $this->generateRedirectTarget($page->getRedirectionUrl(), $page->host);
    }

    /**
     * Turn a redirect target into its final URL: an internal `/slug` that maps to an
     * existing page is route-generated (canonical, host-prefixed); anything else is
     * returned as-is (external URL, or internal target with no page).
     */
    private function generateRedirectTarget(string $url, string $host): string
    {
        if (! str_starts_with($url, '/')) {
            return $url;
        }

        $targetPage = $this->pageRepository->getPageBySlug(ltrim($url, '/'), $host);

        return null !== $targetPage ? $this->routeGenerator->generate($targetPage) : $url;
    }

    /** @noRector */
    private function checkIfUriIsCanonical(Request $request, Page $page): false|string
    {
        $requestUri = $request->getRequestUri();

        $expected = $this->generateUrl('pushword_page', ['slug' => $page->getRealSlug()]);

        if ($requestUri !== $expected) {
            return $request->getBasePath().$expected;
        }

        return false;
    }
}
