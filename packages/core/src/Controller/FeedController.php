<?php

namespace Pushword\Core\Controller;

use DateTime;
use LogicException;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;

use function Safe\preg_match;

use Symfony\Bundle\FrameworkBundle\Translation\Translator;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Translation\DataCollectorTranslator;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as Twig;

/**
 * Handles RSS feed generation for main feed and page-specific feeds.
 */
final class FeedController extends AbstractPushwordController
{
    /** @var DataCollectorTranslator|Translator */
    private readonly TranslatorInterface $translator;

    public function __construct(
        RequestStack $requestStack,
        AppPool $apps,
        Twig $twig,
        private readonly ParameterBagInterface $params,
        private readonly PageRepository $pageRepository,
        TranslatorInterface $translator,
    ) {
        if (! $translator instanceof DataCollectorTranslator && ! $translator instanceof Translator) {
            throw new LogicException('A symfony codebase changed make this hack impossible (cf setLocale). Get `'.$translator::class.'`');
        }

        parent::__construct($requestStack, $apps, $twig);

        $this->translator = $translator;
    }

    /**
     * Show last created pages in an XML Feed.
     */
    #[Route('/{_locale}feed.xml', name: 'pushword_page_main_feed', requirements: ['_locale' => RoutePatterns::LOCALE], methods: ['GET', 'HEAD'], priority: -20)]
    #[Route('/{host}/{_locale}feed.xml', name: 'custom_host_pushword_page_main_feed', requirements: ['_locale' => RoutePatterns::LOCALE, 'host' => RoutePatterns::HOST], methods: ['GET', 'HEAD'], priority: -21)]
    public function showMain(Request $request): Response
    {
        $this->initHost($request);

        $locale = '' !== $request->getLocale() ? rtrim($request->getLocale(), '/') : $this->apps->getApp()->getDefaultLocale();
        $LocaleHomepage = $this->findPage($request, $locale, false);
        $slug = 'homepage';
        $page = $LocaleHomepage ?? $this->findPage($request, $slug);
        if (! $page instanceof Page) {
            throw $this->createNotFoundException('The page `'.$slug.'` was not found');
        }

        $request->setLocale($page->getLocale());

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

    /**
     * Show child pages of a page in an XML Feed.
     */
    #[Route('/{host}/{slug}.xml', name: 'custom_host_pushword_page_feed', requirements: ['slug' => RoutePatterns::SLUG, 'host' => RoutePatterns::HOST], methods: ['GET', 'HEAD'], priority: -40)]
    #[Route('/{slug}.xml', name: 'pushword_page_feed', requirements: ['slug' => RoutePatterns::SLUG], methods: ['GET', 'HEAD'], priority: -50)]
    public function show(Request $request, string $slug = ''): Response
    {
        $this->initHost($request);

        if ('homepage' === $slug) {
            return $this->redirectToRoute('pushword_page_feed', ['slug' => 'index'], Response::HTTP_MOVED_PERMANENTLY);
        }

        $page = $this->getPageElse404($request, $slug);

        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml');

        if (! $page->hasChildrenPages()) {
            throw $this->createNotFoundException();
        }

        $request->setLocale($page->getLocale());

        return $this->render(
            $this->getView('/page/rss.xml.twig'),
            [...['page' => $page], ...$this->apps->getApp()->getParamsForRendering()],
            $response
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
            '' !== $requestedLocale ? $requestedLocale : $this->params->get('kernel.default_locale'),
            $limit
        )
        ->orderBy('p.publishedAt', 'DESC')
        ->getQuery()->getResult();
    }

    private function getPageElse404(Request $request, string $slug, bool $extractPager = false): Page
    {
        if ('' === $slug) {
            $slug = 'homepage';
        }

        return $this->getPage($request, $slug, $extractPager);
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

        if ('' === $page->getLocale()) { // avoid bc break
            $page->setLocale($this->apps->getApp()->getLocale());
        }

        $this->translator->setLocale($page->getLocale());

        // Check if page is public
        if ($page->getCreatedAt() > new DateTime() && ! $this->isGranted('ROLE_EDITOR')) {
            return null;
        }

        $this->apps->setCurrentPage($page);

        return $page;
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

    private function normalizeSlug(?string $slug): string
    {
        return (null === $slug || '' === $slug) ? 'homepage' : rtrim(strtolower($slug), '/');
    }
}
