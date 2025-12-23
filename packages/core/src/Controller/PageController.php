<?php

namespace Pushword\Core\Controller;

use DateTime;
use LogicException;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;

use function Safe\preg_match;

use Symfony\Bundle\FrameworkBundle\Translation\Translator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Translation\DataCollectorTranslator;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as Twig;

/**
 * Handles page rendering with support for multiple locales and multi-domain.
 *
 * ## Routes for pages are organized by priority to ensure correct matching.
 *
 * For each route, there are typically 2 versions:
 * - One without host
 * - One with host prefix for multi-domain support
 *
 * - Sitemap routes (in SitemapController)
 * - Main feed routes (in FeedController)
 * - Page feed routes (for child pages)
 * - Page show routes (main page display)
 * - Pager routes (never caught but allow generating paginated URLs)
 */
final class PageController extends AbstractPushwordController
{
    /** @var DataCollectorTranslator|Translator */
    private readonly TranslatorInterface $translator;

    public function __construct(
        AppPool $apps,
        Twig $twig,
        private readonly PageRepository $pageRepository,
        TranslatorInterface $translator,
    ) {
        if (! $translator instanceof DataCollectorTranslator && ! $translator instanceof Translator) {
            throw new LogicException('A symfony codebase changed make this hack impossible (cf setLocale). Get `'.$translator::class.'`');
        }

        parent::__construct($apps, $twig);

        $this->translator = $translator;
    }

    public function setHost(string $host): self
    {
        $this->apps->switchCurrentApp($host);

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
        $page = $this->getPageElse404($request, $slug, true);

        // SEO redirection if we are not on the good URI (for exemple /fr/tagada instead of /tagada)
        $host = $request->query->get('host');
        if (
            ('' !== $host && $host === $request->getHost()) // avoid redir when using custom_host route
            && false !== $redirect = $this->checkIfUriIsCanonical($request, $page)) {
            return $this->redirect($redirect, Response::HTTP_MOVED_PERMANENTLY);
        }

        // Maybe the page is a redirection
        if ($page->hasRedirection()) {
            return $this->redirect($page->getRedirection(), $page->getRedirectionCode());
        }

        // TODO: move it to event (onRequest + onPageLoad)
        $request->setLocale($page->locale);
        $this->translator->setLocale($page->locale);

        return $this->showPage($page);
    }

    public function showPage(Page $page): Response
    {
        $params = [...['page' => $page], ...$this->apps->getApp()->getParamsForRendering()];

        $view = $this->getView($page->getTemplate() ?? '/page/page.html.twig');

        $response = new Response();

        // used ???
        // if (\is_array($headers = $page->getCustomProperty('headers'))) {
        //     foreach ($headers as $header) {
        //         $response->headers->set($header[0], $header[1]);
        //     }
        // }

        return $this->render($view, $params, $response);
    }

    private function getPageElse404(Request $request, string $slug, bool $extractPager = false): Page
    {
        if ('' === $slug) {
            $slug = 'homepage';
        }

        return $this->getPage($request, $slug, $extractPager);
    }

    private function extractPager(
        Request $request,
        string &$slug,
    ): ?Page {
        if (1 !== preg_match('#(/([1-9]\d*)|^([1-9]\d*))$#', $slug, $match)) {
            return null;
        }

        /** @var array{1: string, 2: string, 3:string} $match */
        $unpaginatedSlug = substr($slug, 0, -\strlen($match[1]));
        $request->attributes->set('pager', (int) $match[2] >= 1 ? $match[2] : $match[3]);
        $request->attributes->set('slug', $unpaginatedSlug);

        return $this->findPage($request, $unpaginatedSlug);
    }

    private function getPage(
        Request $request,
        string &$slug,
        bool $extractPager = false
    ): Page {
        return $this->findPage($request, $slug, $extractPager) ?? throw $this->createNotFoundException();
    }

    private function findPage(
        Request $request,
        string &$slug,
        bool $extractPager = false
    ): ?Page {
        $slug = $this->normalizeSlug($slug);
        $page = $this->pageRepository->getPage($slug, $this->apps->get()->getHostForDoctrineSearch(), true);

        if (! $page instanceof Page && $extractPager) {
            $page = $this->extractPager($request, $slug);
        }

        // Check if page exist
        if (! $page instanceof Page) {
            return null;
        }

        if ('' === $page->locale) { // avoid bc break
            $page->locale = $this->apps->getApp()->getLocale();
        }

        $this->translator->setLocale($page->locale);

        // Check if page is public
        if ($page->createdAt > new DateTime() && ! $this->isGranted('ROLE_EDITOR')) {
            return null;
        }

        $this->apps->setCurrentPage($page); // used by Router ???

        return $page;
    }

    private function normalizeSlug(?string $slug): string
    {
        return (null === $slug || '' === $slug) ? 'homepage' : rtrim(strtolower($slug), '/');
    }

    /**
     * @noRector
     */
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
