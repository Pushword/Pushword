<?php

namespace Pushword\Core\Controller;

use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\PageInterface;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Repository\Repository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\FrameworkBundle\Translation\Translator;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\DataCollectorTranslator;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as Twig;

final class PageController extends AbstractController
{
    use RenderTrait;

    private AppConfig $app;

    private Twig $twig;

    /** @var DataCollectorTranslator|Translator */
    private TranslatorInterface $translator;

    public function __construct(
        private ParameterBagInterface $params,
        private EntityManagerInterface $em,
        private AppPool $apps,
        TranslatorInterface $translator
    ) {
        if (! $translator instanceof DataCollectorTranslator && ! $translator instanceof Translator) {
            throw new LogicException('A symfony codebase changed make this hack impossible (cf setLocale). Get `'.$translator::class.'`');
        }

        $this->translator = $translator;
    }

    public function show(Request $request, ?string $slug, string $host = ''): Response
    {
        $page = $this->getPageElse404($request, $slug, $host, true);

        // SEO redirection if we are not on the good URI (for exemple /fr/tagada instead of /tagada)
        if (
            ('' !== $host && $host === $request->getHost()) // avoid redir when using custom_host route
            && false !== $redirect = $this->checkIfUriIsCanonical($request, $page)) {
            return $this->redirect($redirect, 301);
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
        $params = array_merge(['page' => $page], $this->app->getParamsForRendering());

        $view = $this->getView(null !== $page->getTemplate() ? $page->getTemplate() : '/page/page.html.twig');

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
        return $this->app->getView($path);
    }

    public function showFeed(Request $request, string $slug = '', string $host = ''): Response
    {
        if ('homepage' == $slug) {
            return $this->redirect($this->generateUrl('pushword_page_feed', ['slug' => 'index']), 301);
        }

        $page = $this->getPageElse404($request, $slug, $host);

        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');

        if (! $page->hasChildrenPages()) {
            throw $this->createNotFoundException();
        }

        $request->setLocale($page->getLocale()); // TODO: move it to event (onRequest + onPageLoad)

        return $this->render(
            $this->getView('/page/rss.xml.twig'),
            array_merge(['page' => $page], $this->app->getParamsForRendering()),
            $response
        );
    }

    /**
     * Show Last created page in an XML Feed.
     */
    public function showMainFeed(Request $request, string $host = ''): Response
    {
        $this->setApp($host);
        $locale = '' !== $request->getLocale() ? rtrim($request->getLocale(), '/') : $this->app->getDefaultLocale();
        $LocaleHomepage = $this->getPage($request, $locale, $host, false);
        $slug = 'homepage';
        $page = null !== $LocaleHomepage ? $LocaleHomepage : $this->getPage($request, $slug, $host);
        $request->setLocale($page->getLocale());

        $params = [
            'pages' => $this->getPages($request, 5),
            'page' => $page,
            'feedUri' => ($this->params->get('kernel.default_locale') == $locale ? '' : $locale.'/').'feed.xml',
        ];

        return $this->render(
            $this->getView('/page/rss.xml.twig'),
            array_merge($params, $this->app->getParamsForRendering())
        );
    }

    public function showSitemap(Request $request, string $_format, string $host = ''): Response
    {
        $this->setApp($host);
        $pages = $this->getPages($request, null);

        if (! \is_array($pages) || ! isset($pages[0])) {
            throw $this->createNotFoundException();
        }

        return $this->render(
            $this->getView('/page/sitemap.'.$_format.'.twig'),
            [
                'pages' => $pages,
                'app_base_url' => $this->app->getBaseUrl(),
            ]
        );
    }

    public function showRobotsTxt(string $host = ''): Response
    {
        $this->setApp($host);

        $response = new Response();
        $response->headers->set('Content-Type', 'text/plain');

        return $this->render(
            $this->getView('/page/robots.txt.twig'),
            [
                'app_base_url' => $this->app->getBaseUrl(),
            ],
            $response
        );
    }

    /**
     .
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

    public function setApp(PageInterface|string $host): void
    {
        $this->app = $this->apps->switchCurrentApp($host)->get();
    }

    /**
     * @psalm-suppress NullableReturnStatement
     * @psalm-suppress InvalidNullableReturnType
     * @noRector
     */
    private function getPageElse404(Request $request, ?string &$slug, string $host, bool $extractPager = false): PageInterface
    {
        return $this->getPage($request, $slug, $host, true, $extractPager); // @phpstan-ignore-line
    }

    private function extractPager(
        Request $request,
        string &$slug,
        string $host,
        bool $throwException
    ): ?PageInterface {
        if (1 !== \Safe\preg_match('#(/([1-9][0-9]*)|^([1-9][0-9]*))$#', $slug, $match)) {
            return null;
        }

        $unpaginatedSlug = \Safe\substr($slug, 0, -(\strlen($match[1])));
        $request->attributes->set('pager', (int) $match[2] >= 1 ? $match[2] : $match[3]);
        $request->attributes->set('slug', $unpaginatedSlug);

        return $this->getPage($request, $unpaginatedSlug, $host, $throwException);
    }

    /** @psalm-suppress UndefinedInterfaceMethod */
    private function getPage(
        Request $request,
        ?string &$slug,
        string $host,
        bool $throwException = true,
        bool $extractPager = false
    ): ?PageInterface {
        $slug = $this->noramlizeSlug($slug);
        $page = $this->getPageRepository()->getPage($slug, '' !== $host ? $host : [(string) $this->apps->getMainHost(), ''], true);

        if (! $page instanceof PageInterface && $extractPager) {
            $page = $this->extractPager($request, $slug, $host, $throwException);
        }

        // Check if page exist
        if (! $page instanceof PageInterface) {
            if ($throwException) {
                throw $this->createNotFoundException();
            } else {
                return null;
            }
        }

        if ('' === $page->getLocale()) { // avoid bc break
            $page->setLocale($this->app->getDefaultLocale());
        }

        $this->translator->setLocale($page->getLocale());

        // Check if page is public
        if ($page->getCreatedAt() > new \DateTimeImmutable() && ! $this->isGranted('ROLE_EDITOR')) {
            if ($throwException) {
                throw $this->createNotFoundException();
            } else {
                return null;
            }
        }

        $this->setApp($page); // permit to load currentPage in Apps (used by Router)

        return $page;
    }

    private function noramlizeSlug(?string $slug): string
    {
        return (null === $slug || '' === $slug) ? 'homepage' : rtrim(strtolower($slug), '/');
    }

    /**
     * @noRector
     *
     * @return false|string
     */
    private function checkIfUriIsCanonical(Request $request, PageInterface $page)
    {
        $requestUri = $request->getRequestUri();

        $expected = $this->generateUrl('pushword_page', ['slug' => $page->getRealSlug()]);

        if ($requestUri !== $expected) {
            return $request->getBasePath().$expected;
        }

        return false;
    }
}
