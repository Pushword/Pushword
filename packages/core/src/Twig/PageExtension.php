<?php

namespace Pushword\Core\Twig;

use InvalidArgumentException;
use LogicException;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;
use Pagerfanta\PagerfantaInterface;
use Pagerfanta\RouteGenerator\RouteGeneratorFactoryInterface;
use Pagerfanta\Twig\View\TwigView;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Router\PushwordRouteGenerator;
use Pushword\Core\Utils\StringToDQLCriteria;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment as Twig;

final class PageExtension
{
    public function __construct(
        private readonly PageRepository $pageRepo,
        public PushwordRouteGenerator $router,
        private readonly AppPool $apps,
        public Twig $twig,
        private readonly RouteGeneratorFactoryInterface $routeGeneratorFactory,
        private readonly RequestStack $requestStack
    ) {
    }

    #[AsTwigFunction('pageContainsBlock')]
    public function pageContainsBlock(Page $page, string $blockId): bool
    {
        $mainContent = $page->getMainContent();

        if (str_contains($mainContent, '"anchor":"'.$blockId.'"')) {
            return true;
        }

        if (str_contains($mainContent, ' id="'.$blockId.'"')) {
            return true;
        }

        return str_contains($mainContent, ' id='.$blockId.'');
    }

    #[AsTwigFunction('breadcrumb_list_position')]
    public function getBreadcrumbListPosition(Page $page): int
    {
        if (null !== ($parentPage = $page->getParentPage())) {
            return $this->getBreadcrumbListPosition($parentPage) + 1;
        }

        return 1;
    }

    /**
     * @param string|string[]|null $host
     *
     * @return string[]
     */
    #[AsTwigFunction('page_uri_list')]
    public function getPageUriList(string|array|null $host = null): array
    {
        $host ??= $this->apps->getCurrentPage()?->getHost();
        $host ??= $this->apps->getMainHost() ?? [];

        return $this->pageRepo->getPageUriList($host);
    }

    /**
     * @param string|string[]|null               $host
     * @param array<(string|int), string>|string $order
     * @param array<mixed>|string                $where
     * @param int|array<(string|int), int>       $max
     *
     * @return Page[]
     */
    #[AsTwigFunction('pages')]
    public function getPublishedPages($host = null, array|string $where = [], array|string $order = 'priority,publishedAt', array|int $max = 0, bool $withRedirection = false): array
    {
        $currentPage = $this->apps->getCurrentPage();
        $where = [\is_array($where) ? $where : (new StringToDQLCriteria($where, $currentPage))->retrieve()];
        $where[] = ['id',  '<>', $currentPage?->getId() ?? 0];

        $order = '' === $order ? 'publishedAt,priority' : $order;
        $order = \is_string($order) ? ['key' => str_replace(['↑', '↓'], ['ASC', 'DESC'], $order)]
            : ['key' => $order[0], 'direction' => $order[1]];

        return $this->pageRepo->getPublishedPages($host ?? $this->apps->getMainHost() ?? [], $where, $order, $this->getLimit($max), $withRedirection);
    }

    /**
     * @param string|string[]|null $host
     */
    #[AsTwigFunction('p')]
    public function getPublishedPage(string $slug, $host = null): ?Page
    {
        $pages = $this->pageRepo->getPublishedPages(
            $host ?? $this->apps->getMainHost() ?? [],
            [['key' => 'slug', 'operator' => '=', 'value' => $slug]],
            [],
            1,
            false
        );

        return $pages[0] ?? null;
    }

    private function getCurrentRequest(): ?Request
    {
        return $this->requestStack->getCurrentRequest();
    }

    /**
     * @return array<string, string|null>
     */
    private function getPagerRouteParams(): array
    {
        $params = [];
        $currentRequest = $this->getCurrentRequest();
        if (null !== $this->apps->getCurrentPage()) {
            // normally, only used in admin
            $params['slug'] = $this->apps->getCurrentPage()->getSlug();
            $params['host'] = $this->apps->getCurrentPage()->getHost();
        }

        if (null !== $currentRequest && \is_string($slug = $currentRequest->attributes->get('slug'))) {
            $params['slug'] = rtrim($slug, '/');
        }

        if (null !== $currentRequest && null !== ($host = $currentRequest->request->get('host'))) {
            $params['host'] = (string) $host;
        }

        return $params;
    }

    private function getPagerRouteName(): string
    {
        $currentRequest = $this->getCurrentRequest();
        $pagerRouter = null !== $currentRequest ? $currentRequest->attributes->getString('_route') : 'pushword_page';
        $pagerRouter .= null !== $currentRequest && '' === $currentRequest->attributes->getString('slug') ? '_homepage' : '';

        return $pagerRouter.'_pager';
    }

    /**
     * @param string|string[] $host
     *
     * @return string[]
     */
    private function getHost(array|string $host, ?Page $currentPage = null): array
    {
        if ('' !== $host) {
            return is_string($host) ? [$host] : $host;
        }

        if (null !== $currentPage) {
            return [$currentPage->getHost()];
        }

        return [$this->apps->get()->getMainHost(), ''];
    }

    /**
     * @param string|array<mixed>                $search
     * @param string|array<(string|int), string> $order
     * @param int|array<(string|int)>            $max         if max is int => max result,
     *                                                        if max is array => paginate where 0
     *                                                        => item per page and 1 (fac) maxPage
     *                                                        array is a legacy alias for maxPages
     * @param Page|null                          $currentPage DO NOT USE OUTSIDE PUSHWORD PACKAGES
     * @param string|string[]                    $host        DO NOT USE OUTSIDE PUSHWORD PACKAGES
     */
    #[AsTwigFunction('pages_list', isSafe: ['html'], needsEnvironment: false)]
    public function renderPagesList(
        array|string $search = '',
        int|array|string $max = 0,
        array|string $order = 'publishedAt,priority',
        string $view = '',
        int|string $maxPages = 0,
        string $wrapperClass = '',
        string $id = '',

        // next properties are not documented, do not use outside pushword packages
        array|string $host = '',
        ?Page $currentPage = null,
    ): string {
        $currentPage ??= $this->apps->getCurrentPage();

        // normalize args
        $maxPages = (int) $maxPages;
        if (is_array($max)) {
            if (0 !== $maxPages) {
                throw new LogicException('maxPages is not supported when max is an array');
            }

            $maxPages = (int) ($max[1] ?? 0);
            $max = (int) ($max[0] ?? 0);
        }

        $max = (int) $max;
        if ($max < 1) {
            throw new LogicException();
        }

        // end normalize args

        $view = 'card' === $view ? '/component/pages_list_card.html.twig'
            : (\in_array($view, ['', 'list'], true) ? '/component/pages_list.html.twig'
                : $view);

        $search = \is_array($search) ? $search : (new StringToDQLCriteria($search, $currentPage))->retrieve();

        $order = '' === $order ? 'publishedAt,priority' : $order;
        $order = \is_string($order) ? ['key' => str_replace(['↑', '↓'], ['ASC', 'DESC'], $order)]
            : ['key' => $order[0], 'direction' => $order[1]];

        $host = $this->getHost($host, $currentPage);
        $queryBuilder = $this->pageRepo->getPublishedPageQueryBuilder(
            $host,
            $search,
            $order,
            $max,
        );

        if (null !== $currentPage) {
            $queryBuilder->andWhere('p.id <> '.($currentPage->getId() ?? 0));
        }

        if ($maxPages > 1) {
            /** @var Page[] */
            $pages = $queryBuilder->getQuery()->getResult();
            $limit = $this->getLimit($max);
            if (0 !== $limit) {
                $pages = \array_slice($pages, 0, $limit);
            }

            $pagerfanta = (new Pagerfanta(new ArrayAdapter($pages)))
                ->setMaxNbPages($maxPages)
                ->setMaxPerPage($max)
                ->setCurrentPage($this->getCurrentPage());
            $pages = $pagerfanta->getCurrentPageResults();
        } else {
            $pages = $queryBuilder->getQuery()->getResult();
        }

        $template = $this->apps->get()->getView($view);

        $rendered = $this->twig->render($template, [
            'pager_route' => $this->getPagerRouteName(),
            'pager_route_params' => $this->getPagerRouteParams(),
            'pages' => $pages,
            'pager' => $pagerfanta ?? null,
            'id' => $id,
            'wrapperClass' => $wrapperClass,
        ]);

        // $rendered = preg_replace('/\n+/', '\n', $rendered);
        // $rendered = trim($rendered);

        return $rendered;
    }

    /**
     * @param array<string, mixed>       $options
     * @param PagerfantaInterface<mixed> $pagerfanta
     *
     * @throws InvalidArgumentException if the $viewName argument is an invalid type
     */
    #[AsTwigFunction('pager', isSafe: ['html'], needsEnvironment: false)]
    public function renderPager(
        PagerfantaInterface $pagerfanta,
        array $options = [],
        string $template = '/component/pager.html.twig'
    ): string {
        $pagerFantaTwigView = new TwigView($this->twig, $this->apps->get()->getView($template));

        return $pagerFantaTwigView
            ->render($pagerfanta, $this->routeGeneratorFactory->create($options), $options)
            .($pagerfanta->hasNextPage() ? '<!-- pager:'.$pagerfanta->getNextPage().' -->' : '');
    }

    /**
     * @param int|array<(string|int), int> $max
     */
    private function getLimit(array|int $max): int
    {
        return \is_int($max) ? $max : (isset($max[1]) ? $max[1] * $max[0] : 0);
    }

    private function getCurrentPage(): int
    {
        $currentRequest = $this->requestStack->getCurrentRequest();

        if (null === $currentRequest) {
            // throw new Exception('no current request'); // only in test ?!
            return 1;
        }

        return $currentRequest->attributes->getInt('pager', 1);
    }
}
