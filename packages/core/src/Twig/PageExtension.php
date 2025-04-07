<?php

namespace Pushword\Core\Twig;

use InvalidArgumentException;
use LogicException;
use Override;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;
use Pagerfanta\PagerfantaInterface;
use Pagerfanta\RouteGenerator\RouteGeneratorFactoryInterface;
use Pagerfanta\Twig\View\TwigView;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Component\EntityFilter\ManagerPool;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Router\PushwordRouteGenerator;
use Pushword\Core\Utils\StringToDQLCriteria;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment as Twig;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PageExtension extends AbstractExtension
{
    public function __construct(
        private readonly PageRepository $pageRepo,
        public PushwordRouteGenerator $router,
        private readonly AppPool $apps,
        public Twig $twig,
        private readonly ManagerPool $entityFilterManagerPool,
        private readonly RouteGeneratorFactoryInterface $routeGeneratorFactory,
        private readonly RequestStack $requestStack
    ) {
    }

    /**
     * @return TwigFunction[]
     */
    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('pages', $this->getPublishedPages(...)),
            new TwigFunction('page_uri_list', $this->getPageUriList(...)),
            new TwigFunction('p', $this->getPublishedPage(...)),
            new TwigFunction('pw', $this->entityFilterManagerPool->getProperty(...)),
            new TwigFunction('breadcrumb_list_position', $this->getBreadcrumbListPosition(...)),
            new TwigFunction('pageContainsBlock', $this->pageContainsBlock(...)),

            new TwigFunction('pager', $this->renderPager(...), AppExtension::options()),
            new TwigFunction('pages_list', $this->renderPagesList(...), AppExtension::options()),
        ];
    }

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
    public function getPageUriList(string|array|null $host = null): array
    {
        return $this->pageRepo->getPageUriList($host ?? $this->apps->getMainHost() ?? []);
    }

    /**
     * @param string|string[]|null               $host
     * @param array<(string|int), string>|string $order
     * @param array<mixed>|string                $where
     * @param int|array<(string|int), int>       $max
     *
     * @return Page[]
     */
    public function getPublishedPages($host = null, array|string $where = [], array|string $order = 'priority,publishedAt', array|int $max = 0, bool $withRedirection = false): array
    {
        $currentPage = $this->apps->getCurrentPage();
        $where = \is_array($where) ? $where : (new StringToDQLCriteria($where, $currentPage))->retrieve();
        $where[] = ['id',  '<>', $currentPage?->getId() ?? 0];

        $order = '' === $order ? 'publishedAt,priority' : $order;
        $order = \is_string($order) ? ['key' => str_replace(['↑', '↓'], ['ASC', 'DESC'], $order)]
            : ['key' => $order[0], 'direction' => $order[1]];

        return $this->pageRepo->getPublishedPages($host ?? $this->apps->getMainHost() ?? [], $where, $order, $this->getLimit($max), $withRedirection);
    }

    /**
     * @param string|string[]|null $host
     */
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
        if (null !== $currentRequest && \is_string($slug = $currentRequest->attributes->get('slug'))) {
            $params['slug'] = rtrim($slug, '/');
        } elseif (null !== $this->apps->getCurrentPage()) {
            // normally, only used in admin
            $params['slug'] = $this->apps->getCurrentPage()->getSlug();
            $params['host'] = $this->apps->getCurrentPage()->getHost();
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
     * @param string|array<mixed>                $search
     * @param string|array<(string|int), string> $order
     * @param string|string[]                    $host
     * @param int|array<(string|int), int>       $max    if max is int => max result,
     *                                                   if max is array => paginate where 0 => item per page and 1 (fac) maxPage
     */
    public function renderPagesList(
        array|string $search = '',
        array|int $max = 0,
        array|string $order = 'publishedAt,priority',
        string $view = '',
        array|string $host = '',
        ?Page $currentPage = null
    ): string {
        $currentPage ??= $this->apps->getCurrentPage();

        $view = 'card' === $view ?
                '/component/pages_list_card.html.twig'
                : (\in_array($view, ['', 'list'], true) ? '/component/pages_list.html.twig' : $view);

        $search = \is_array($search) ? $search : (new StringToDQLCriteria($search, $currentPage))->retrieve();

        $order = '' === $order ? 'publishedAt,priority' : $order;
        $order = \is_string($order) ? ['key' => str_replace(['↑', '↓'], ['ASC', 'DESC'], $order)]
            : ['key' => $order[0], 'direction' => $order[1]];

        $queryBuilder = $this->pageRepo->getPublishedPageQueryBuilder(
            '' !== $host ? $host : [$this->apps->get()->getMainHost(), ''],
            $search,
            $order,
            $this->getLimit($max)
        );

        if (null !== $currentPage) {
            $queryBuilder->andWhere('p.id <> '.($currentPage->getId() ?? 0));
        }

        if (\is_array($max) && isset($max[1]) && $max[1] > 1) {
            /** @var Page[] */
            $pages = $queryBuilder->getQuery()->getResult();
            $limit = $this->getLimit($max);
            if (0 !== $limit) {
                $pages = \array_slice($pages, 0, $limit);
            }

            if ($max[0] < 1) {
                throw new LogicException();
            }

            $pagerfanta = (new Pagerfanta(new ArrayAdapter($pages)))
                ->setMaxNbPages($max[1])
                ->setMaxPerPage($max[0])
                ->setCurrentPage($this->getCurrentPage());
            $pages = $pagerfanta->getCurrentPageResults();
        } else {
            $pages = $queryBuilder->getQuery()->getResult();
        }

        $template = $this->apps->get()->getView($view);

        return $this->twig->render($template, [
            'pager_route' => $this->getPagerRouteName(),
            'pager_route_params' => $this->getPagerRouteParams(),
            'pages' => $pages,
            'pager' => $pagerfanta ?? null,
        ]);
    }

    /**
     * @param array<string, mixed>       $options
     * @param PagerfantaInterface<mixed> $pagerfanta
     *
     * @throws InvalidArgumentException if the $viewName argument is an invalid type
     */
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

        return \intval($currentRequest->attributes->getInt('pager', 1));
    }
}
