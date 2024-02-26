<?php

namespace Pushword\Core\Controller;

use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\PageInterface;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Repository\Repository;

use function Safe\preg_match;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\FrameworkBundle\Translation\Translator;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\DataCollectorTranslator;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PageController extends AbstractController
{
    use RenderTrait;

    /** @var DataCollectorTranslator|Translator */
    private readonly TranslatorInterface $translator;

    public function __construct(
        RequestStack $requestStack,
        private readonly ParameterBagInterface $params,
        private readonly EntityManagerInterface $em,
        private readonly AppPool $apps,
        TranslatorInterface $translator
    ) {
        if (! $translator instanceof DataCollectorTranslator && ! $translator instanceof Translator) {
            throw new \LogicException('A symfony codebase changed make this hack impossible (cf setLocale). Get `'.$translator::class.'`');
        }

        $this->initHost($requestStack);
        $this->translator = $translator;
    }

    private function initHost(RequestStack|Request $request): void
    {
        $request = $request instanceof Request ? $request : $request->getCurrentRequest();

        if (! $request instanceof Request) {
            return;
        }

        $host = \strval($request->attributes->get('host', '')); // @phpstan-ignore-line
        if ('' !== $host) {
            $this->apps->switchCurrentApp($host);

            return;
        }

        $host = $this->apps->findHost($request->getHost());
        if ('' !== $host) {
            $this->apps->switchCurrentApp($host);
        }
    }

    public function setHost(string $host): self
    {
        $this->apps->switchCurrentApp($host);

        return $this;
    }

    public function show(Request $request, ?string $slug): Response
    {
        $this->initHost($request);

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

        $request->setLocale($page->getLocale()); // TODO: move it to event (onRequest + onPageLoad)

        return $this->showPage($page);
    }

    public function showPage(PageInterface $page): Response
    {
        $params = [...['page' => $page], ...$this->apps->getApp()->getParamsForRendering()];

        $view = $this->getView($page->getTemplate() ?? '/page/page.html.twig');

        $response = new Response();
        if (\is_array($headers = $page->getCustomProperty('headers'))) {
            foreach ($headers as $header) {
                $response->headers->set($header[0], $header[1]);
            }
        }

        return $this->render($view, $params, $response);
    }

    private function getView(string $path): string
    {
        return $this->apps->getApp()->getView($path);
    }

    public function showFeed(Request $request, string $slug = ''): Response
    {
        $this->initHost($request);

        if ('homepage' == $slug) {
            return $this->redirect($this->generateUrl('pushword_page_feed', ['slug' => 'index']), Response::HTTP_MOVED_PERMANENTLY);
        }

        $page = $this->getPageElse404($request, $slug);

        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');

        if (! $page->hasChildrenPages()) {
            throw $this->createNotFoundException();
        }

        $request->setLocale($page->getLocale()); // TODO: move it to event (onRequest + onPageLoad)

        return $this->render(
            $this->getView('/page/rss.xml.twig'),
            [...['page' => $page], ...$this->apps->getApp()->getParamsForRendering()],
            $response
        );
    }

    /**
     * Show Last created page in an XML Feed.
     */
    public function showMainFeed(Request $request): Response
    {
        $this->initHost($request);

        $locale = '' !== $request->getLocale() ? rtrim($request->getLocale(), '/') : $this->apps->getApp()->getDefaultLocale();
        $LocaleHomepage = $this->getPage($request, $locale, false);
        $slug = 'homepage';
        $page = $LocaleHomepage ?? $this->getPage($request, $slug);
        if (! $page instanceof PageInterface) {
            throw $this->createNotFoundException('The page `'.$slug.'` was not found');
        }

        $request->setLocale($page->getLocale());

        $params = [
            'pages' => $this->getPages($request, 5),
            'page' => $page,
            'feedUri' => ($this->params->get('kernel.default_locale') == $locale ? '' : $locale.'/').'feed.xml',
        ];

        return $this->render(
            $this->getView('/page/rss.xml.twig'),
            [...$params, ...$this->apps->getApp()->getParamsForRendering()]
        );
    }

    public function showSitemap(Request $request, string $_format): Response
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

    public function showRobotsTxt(): Response
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/plain');

        return $this->render(
            $this->getView('/page/robots.txt.twig'),
            [
                'app_base_url' => $this->apps->getApp()->getBaseUrl(),
            ],
            $response
        );
    }

    /**
     * .
     *
     * @return mixed //array<PageInterface>
     */
    private function getPages(Request $request, ?int $limit = null)
    {
        $requestedLocale = rtrim($request->getLocale(), '/');

        return $this->getPageRepository()->getIndexablePagesQuery(
            (string) $this->apps->getMainHost(),
            '' !== $requestedLocale ? $requestedLocale : $this->params->get('kernel.default_locale'),
            $limit
        )
        ->orderBy('p.publishedAt', Criteria::DESC)
        ->getQuery()->getResult();
    }

    private function getPageRepository(): PageRepository
    {
        return Repository::getPageRepository($this->em, $this->params->get('pw.entity_page')); // @phpstan-ignore-line
    }

    /**
     * @psalm-suppress NullableReturnStatement
     * @psalm-suppress InvalidNullableReturnType
     *
     * @noRector
     */
    private function getPageElse404(Request $request, ?string &$slug, bool $extractPager = false): PageInterface
    {
        return $this->getPage($request, $slug, true, $extractPager); // @phpstan-ignore-line
    }

    private function extractPager(
        Request $request,
        string &$slug,
        bool $throwException
    ): ?PageInterface {
        if (1 !== preg_match('#(/([1-9]\d*)|^([1-9]\d*))$#', $slug, $match)) {
            return null;
        }

        $unpaginatedSlug = substr($slug, 0, -\strlen((string) $match[1]));
        $request->attributes->set('pager', (int) $match[2] >= 1 ? $match[2] : $match[3]);
        $request->attributes->set('slug', $unpaginatedSlug);

        return $this->getPage($request, $unpaginatedSlug, $throwException);
    }

    /** @psalm-suppress UndefinedInterfaceMethod */
    private function getPage(
        Request $request,
        ?string &$slug,
        bool $throwException = true,
        bool $extractPager = false
    ): ?PageInterface {
        $slug = $this->noramlizeSlug($slug);
        $page = $this->getPageRepository()->getPage($slug, $this->apps->get()->getHostForDoctrineSearch(), true);

        if (! $page instanceof PageInterface && $extractPager) {
            $page = $this->extractPager($request, $slug, $throwException);
        }

        // Check if page exist
        if (! $page instanceof PageInterface) {
            if ($throwException) {
                throw $this->createNotFoundException();
            }

            return null;
        }

        if ('' === $page->getLocale()) { // avoid bc break
            $page->setLocale($this->apps->getApp()->getDefaultLocale());
        }

        $this->translator->setLocale($page->getLocale());

        // Check if page is public
        if ($page->getCreatedAt() > new \DateTime() && ! $this->isGranted('ROLE_EDITOR')) {
            if ($throwException) {
                throw $this->createNotFoundException();
            }

            return null;
        }

        $this->apps->setCurrentPage($page); // used by Router ???

        return $page;
    }

    private function noramlizeSlug(?string $slug): string
    {
        return (null === $slug || '' === $slug) ? 'homepage' : rtrim(strtolower($slug), '/');
    }

    /**
     * @noRector
     */
    private function checkIfUriIsCanonical(Request $request, PageInterface $page): false|string
    {
        $requestUri = $request->getRequestUri();

        $expected = $this->generateUrl('pushword_page', ['slug' => $page->getRealSlug()]);

        if ($requestUri !== $expected) {
            return $request->getBasePath().$expected;
        }

        return false;
    }
}
