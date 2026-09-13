<?php

declare(strict_types=1);

namespace Pushword\StaticGenerator\Generator;

use LogicException;
use Pushword\Core\Controller\FeedController;
use Pushword\Core\Controller\PageController;
use Pushword\Core\Controller\PageResolver;
use Pushword\Core\Entity\Page;
use Pushword\Core\Site\RequestContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Renders exported pages inside the isolated render kernel without HTTP dispatch. */
final readonly class StaticPageRenderer
{
    private const array PAGE_ROUTES = [
        'pushword_page',
        'custom_host_pushword_page',
        'pushword_page_pager',
        'custom_host_pushword_page_pager',
        'pushword_page_homepage_pager',
        'custom_host_pushword_page_homepage_pager',
    ];

    private const array FEED_ROUTES = ['pushword_page_feed', 'custom_host_pushword_page_feed'];

    private TranslatorInterface&LocaleAwareInterface $translator;

    public function __construct(
        private PageController $pageController,
        private FeedController $feedController,
        private RequestContext $requestContext,
        private RequestStack $requestStack,
        private RouterInterface $router,
        TranslatorInterface $translator,
    ) {
        if (! $translator instanceof LocaleAwareInterface) {
            throw new LogicException('The translator must support locales.');
        }

        $this->translator = $translator;
    }

    public function render(Request $request, Page $page, bool $feed = false): Response
    {
        foreach ($this->router->match($request->getPathInfo()) as $name => $value) {
            if (\is_string($name)) {
                $request->attributes->set($name, $value);
            }
        }

        $request->attributes->set('_pushword_page', $page);
        $route = $request->attributes->getString('_route');
        if (! \in_array($route, $feed ? self::FEED_ROUTES : self::PAGE_ROUTES, true)) {
            throw new LogicException('The route is not a page or page feed.');
        }

        $slug = $request->attributes->getString('slug', '');
        $pager = $request->attributes->getInt('pager', 1);
        $this->requestContext->switchSite($page);
        if ('' === $page->locale) {
            $page->locale = $this->requestContext->currentSite->locale;
        }

        $this->requestContext->setRequestContext($page->host, $route, $slug, $pager);
        $request->setLocale($page->locale);
        $previousLocale = $this->translator->getLocale();
        $this->translator->setLocale($page->locale);
        $this->requestStack->push($request);

        try {
            if ($feed) {
                $response = $this->feedController->show($request, $slug);
            } elseif (PageResolver::normalizeSlug($slug) === PageResolver::normalizeSlug($page->getRealSlug())) {
                $response = $this->pageController->showPage($page);
            } else {
                $response = $this->pageController->show($request, $slug);
            }

            $response->setCharset('UTF-8');

            return $response->prepare($request);
        } finally {
            $this->requestStack->pop();
            $this->requestContext->reset();
            $this->translator->setLocale($previousLocale);
        }
    }
}
