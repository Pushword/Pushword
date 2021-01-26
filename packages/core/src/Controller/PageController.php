<?php

namespace Pushword\Core\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\PageInterface as Page;
use Pushword\Core\Repository\Repository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment as Twig;

class PageController extends AbstractController
{
    /**
     * @var ParameterBagInterface
     */
    protected $params;

    /** @var AppPool */
    protected $apps;

    /** @var AppConfig */
    protected $app;

    /** @var Twig */
    protected $twig;

    /** @var */
    protected $em;

    public function __construct(
        ParameterBagInterface $params,
        Twig $twig,
        EntityManagerInterface $em,
        AppPool $apps
    ) {
        $this->em = $em;
        $this->params = $params;
        $this->twig = $twig;
        $this->apps = $apps;
    }

    public function show(?string $slug, string $host = '', Request $request): Response
    {
        $page = $this->getPage($slug, $host);

        // SEO redirection if we are not on the good URI (for exemple /fr/tagada instead of /tagada)
        if ((! $host || $host == $request->getHost())
            && false !== $redirect = $this->checkIfUriIsCanonical($request, $page)) {
            return $this->redirect($redirect[0], $redirect[1]);
        }

        // Maybe the page is a redirection
        if ($page->getRedirection()) {
            return $this->redirect($page->getRedirection(), $page->getRedirectionCode());
        }

        $params = array_merge(['page' => $page], $this->app->getParamsForRendering());

        $view = $this->getView($page->getTemplate() ?: '/page/page.html.twig');

        $response = new Response();
        if ($page->getCustomProperty('headers')) {
            $headers = $page->getCustomProperty('headers');
            foreach ($headers as $header) {
                $response->headers->set($header[0], $header[1]);
            }
        }

        $request->setLocale($page->getLocale()); // TODO: move it to event (onRequest + onPageLoad)

        return $this->render($view, $params, $response);
    }

    protected function getView(string $path): string
    {
        return $this->app->getView($path, $this->twig);
    }

    public function showFeed(?string $slug, ?string $host, Request $request)
    {
        $page = $this->getPage($slug, $host);

        if ('homepage' == $slug) {
            return $this->redirect($this->generateUrl('pushword_page_feed', ['slug' => 'index']), 301);
        }

        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');

        if (! \count($page->getChildrenPages())) {
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
    public function showMainFeed(?string $host, Request $request)
    {
        $this->setApp($host);
        $locale = $request->getLocale() ? rtrim($request->getLocale(), '/') : $this->app->getDefaultLocale();
        $LocaleHomepage = $this->getPage($locale, $host, false);
        $slug = 'homepage';
        $page = $LocaleHomepage ?: $this->getPage($slug, $host);

        $params = [
            'pages' => $this->getPages(5, $request),
            'page' => $page,
            'feedUri' => ($this->params->get('kernel.default_locale') == $locale ? '' : $locale.'/').'feed.xml',
        ];

        return $this->render(
            $this->getView('/page/rss.xml.twig'),
            array_merge($params, $this->app->getParamsForRendering())
        );
    }

    public function showSitemap($_format, ?string $host, Request $request)
    {
        $this->setApp($host);
        $pages = $this->getPages(null, $request);

        if (! $pages) {
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

    public function showRobotsTxt(?string $host)
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

    protected function getPages(?int $limit = null, Request $request)
    {
        $requestedLocale = rtrim($request->getLocale(), '/');

        $pages = $this->getPageRepository()->getIndexablePagesQuery(
            $this->apps->getMainHost(),
            $requestedLocale ?: $this->params->get('kernel.default_locale'),
            $limit
        )->getQuery()->getResult();

        return $pages;
    }

    /**
     * @return \Pushword\Core\Repository\PageRepository
     */
    protected function getPageRepository()
    {
        return Repository::getPageRepository($this->em, $this->params->get('pw.entity_page'));
    }

    protected function setApp($host): void
    {
        $this->app = $this->apps->switchCurrentApp($host)->get();
    }

    protected function getPage(?string &$slug, string $host = '', $throwException = true): ?Page
    {
        $slug = $this->noramlizeSlug($slug);
        $page = $this->getPageRepository()->getPage($slug, $host);

        // Check if page exist
        if (null === $page) {
            if ($throwException) {
                throw $this->createNotFoundException();
            } else {
                return null;
            }
        }

        if (! $page->getLocale()) { // avoid bc break
            $page->setLocale($this->app->getDefaultLocale());
        }

        //if (null !== $request) { $request->setLocale($page->getLocale()); }
        $this->get('translator')->setLocale($page->getLocale());

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

    protected function noramlizeSlug($slug): string
    {
        return (null === $slug || '' === $slug) ? 'homepage' : rtrim(strtolower($slug), '/');
    }

    protected function checkIfUriIsCanonical(Request $request, Page $page)
    {
        $real = $request->getRequestUri();

        $expected = $this->generateUrl('pushword_page', ['slug' => $page->getRealSlug()]);

        if ($real != $expected) {
            return [$request->getBasePath().$expected, 301];
        }

        return false;
    }
}
