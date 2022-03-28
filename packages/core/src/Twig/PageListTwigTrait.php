<?php

namespace Pushword\Core\Twig;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use LogicException;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;
use Pagerfanta\PagerfantaInterface;
use Pagerfanta\RouteGenerator\RouteGeneratorFactoryInterface;
use Pagerfanta\Twig\View\TwigView;
use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\PageInterface;
use Pushword\Core\Repository\Repository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment as Twig;

trait PageListTwigTrait
{
    /**
     * @var class-string<PageInterface>
     */
    private string $pageClass;

    private EntityManagerInterface $em;

    private AppPool $apps;

    /** @required */
    public RequestStack $requestStack;

    abstract public function getApp(): AppConfig;

    /** @required */
    public RouteGeneratorFactoryInterface $routeGeneratorFactory;

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
        if (null !== $this->getCurrentRequest() && \is_string($slug = $this->getCurrentRequest()->attributes->get('slug'))) {
            $params['slug'] = rtrim($slug, '/');
        } elseif (null !== $this->apps->getCurrentPage()) {
            // normally, only used in admin
            $params['slug'] = $this->apps->getCurrentPage()->getSlug();
            $params['host'] = $this->apps->getCurrentPage()->getHost();
        }

        if (null !== ($currentRequest = $this->getCurrentRequest()) && null !== ($host = $currentRequest->request->get('host'))) {
            $params['host'] = \strval($host);
        }

        return $params;
    }

    private function getPagerRouteName(): string
    {
        $pagerRouter = null !== $this->getCurrentRequest() ? $this->getCurrentRequest()->attributes->get('_route') : 'pushword_page';
        $pagerRouter .= null !== $this->getCurrentRequest() && '' === $this->getCurrentRequest()->attributes->get('slug') ? '_homepage' : '';
        $pagerRouter .= '_pager';

        return $pagerRouter;
    }

    /**
     * TODO: documenter.
     *
     * @return array<mixed>
     */
    private function stringToSearch(string $search): array
    {
        $where = [];

        if (str_contains($search, ' OR ')) {
            $searchToParse = explode(' OR ', $search);
            foreach ($searchToParse as $singleSearchToParse) {
                // $where = array_merge($where, $this->stringToSearch($s), ['OR']);
                $where[] = $this->simpleStringToSearch($singleSearchToParse);
                $where[] = 'OR';
            }

            array_pop($where);
        }
        /*elseif (strpos($search, ' AND ') !== false) { // Manage OR and Where seems difficult
            $searchToParse = explode(' AND ', $search);
            foreach ($searchToParse as $s) {
                $where[] = $this->simpleStringToSearch($s);
            }
        }*/ else {
            $where[] = $this->simpleStringToSearch($search);
        }

        return $where;
    }

    /**
     * @return array<mixed>
     */
    private function simpleStringToSearchChildren(string $search): ?array
    {
        if ('children' == strtolower($search) && null !== $this->apps->getCurrentPage()) {
            return ['parentPage', '=', $this->apps->getCurrentPage()->getId()];
        }

        if ('parent_children' == strtolower($search) && null !== $this->apps->getCurrentPage()) {
            if (($parentPage = $this->apps->getCurrentPage()->getParentPage()) === null) {
                throw new Exception('no parent page');
            }

            return ['parentPage', '=', $parentPage->getId()];
        }

        if ('children_children' == strtolower($search)
            && null !== $this->apps->getCurrentPage()
            && $this->apps->getCurrentPage()->hasChildrenPages()) {
            $childrenPage = $this->apps->getCurrentPage()->getChildrenPages()->map(function ($page) { return $page->getId(); })->toArray();

            return ['parentPage', 'IN', $childrenPage];
        }

        return null;
    }

    /**
     * @return array<mixed>
     */
    private function simpleStringToSearch(string $search): array
    {
        if (($return = $this->simpleStringToSearchChildren($search)) !== null) {
            return $return;
        }

        if (str_starts_with($search, 'comment:')) {
            $search = '<!--'.\Safe\substr($search, \strlen('comment:')).'-->';

            return ['key' => 'mainContent', 'operator' => 'LIKE', 'value' => '%'.$search.'%'];
        }

        if (str_starts_with($search, 'slug:')) {
            $search = \Safe\substr($search, \strlen('slug:'));

            return ['key' => 'slug', 'operator' => 'LIKE', 'value' => $search];
        }

        return ['key' => 'mainContent', 'operator' => 'LIKE', 'value' => '%'.$search.'%'];
    }

    /**
     * @param string|array<mixed> $search
     * @param string|array<(string|int), string> $order
     * @param string|string[] $host
     * @param int|array<(string|int), int>    $max    if max is int => max result,
     *                                        if max is array => paginate where 0 => item per page and 1 (fac) maxPage
     */
    public function renderPagesList(
        Twig $twig,
        array|string $search = '',
        array|int $max = 0,
        array|string $order = 'publishedAt,priority',
        string $view = '/component/pages_list.html.twig',
        array|string $host = ''
    ): string {
        if ('card' == $view) {
            $view = '/component/pages_list_card.html.twig';
        }

        $search = \is_array($search) ? $search : $this->stringToSearch($search);

        $order = '' === $order ? 'publishedAt,priority' : $order;
        $order = \is_string($order) ? ['key' => str_replace(['↑', '↓'], ['ASC', 'DESC'], $order)]
            : ['key' => $order[0], 'direction' => $order[1]];

        $queryBuilder = Repository::getPageRepository($this->em, $this->pageClass)
            ->getPublishedPageQueryBuilder(
                '' !== $host ? $host : [$this->getApp()->getMainHost(), ''],
                $search,
                $order,
                $this->getLimit($max)
            );

        if (null !== $this->apps->getCurrentPage()) {
            $queryBuilder->andWhere('p.id <> '.$this->apps->getCurrentPage()->getId());
        }

        if (\is_array($max) && isset($max[1]) && $max[1] > 1) {
            $pages = (array) $queryBuilder->getQuery()->getResult();
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
                ->setCurrentPage($this->getCurrentPage()); // @phpstan-ignore-line
            $pages = $pagerfanta->getCurrentPageResults();
        } else {
            $pages = $queryBuilder->getQuery()->getResult();
        }

        $template = $this->getApp()->getView($view);

        return $twig->render($template, [
            'pager_route' => $this->getPagerRouteName(),
            'pager_route_params' => $this->getPagerRouteParams(),
            'pages' => $pages,
            'pager' => $pagerfanta ?? null,
        ]);
    }

    /**
     * @param array<mixed>               $options
     * @param PagerfantaInterface<mixed> $pagerfanta
     *
     * @throws \InvalidArgumentException if the $viewName argument is an invalid type
     */
    public function renderPager(
        Twig $twig,
        PagerfantaInterface $pagerfanta,
        array $options = [],
        string $template = '/component/pager.html.twig'
    ): string {
        $pagerFantaTwigView = new TwigView($twig, $this->getApp()->getView($template));

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
        if (null === $this->requestStack->getCurrentRequest()) {
            // throw new Exception('no current request'); // only in test ?!
            return 1;
        }

        return \intval($this->requestStack->getCurrentRequest()->attributes->get('pager', 1));
    }
}
