<?php

namespace Pushword\Core\Twig;

use Doctrine\ORM\EntityManagerInterface;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;
use Pagerfanta\PagerfantaInterface;
use Pagerfanta\RouteGenerator\RouteGeneratorFactoryInterface;
use Pagerfanta\Twig\View\TwigView;
use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Repository\Repository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment as Twig;

trait PageListTwigTrait
{
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

    private function getPagerRouteParams(): array
    {
        $params = [];
        if ($this->getCurrentRequest() && $this->getCurrentRequest()->get('slug')) {
            $params['slug'] = rtrim($this->getCurrentRequest()->get('slug'), '/');
        } elseif ($this->apps->getCurrentPage()) {
            // normally, only used in admin
            $params['slug'] = $this->apps->getCurrentPage()->getSlug();
            $params['host'] = $this->apps->getCurrentPage()->getHost();
        }

        if ($this->getCurrentRequest() && $this->getCurrentRequest()->get('host')) {
            $params['host'] = $this->requestStack->getCurrentRequest()->get('host');
        }

        return $params;
    }

    private function getPagerRouteName(): string
    {
        $pagerRouter = $this->getCurrentRequest() ? $this->getCurrentRequest()->get('_route') : 'pushword_page';
        $pagerRouter .= $this->getCurrentRequest() && '' === $this->getCurrentRequest()->get('slug') ? '_homepage' : '';
        $pagerRouter .= '_pager';

        return $pagerRouter;
    }

    /**
     * TODO: documenter.
     */
    private function stringToSearch(string $search): array
    {
        $where = [];

        if (false !== strpos($search, ' OR ')) {
            $searchToParse = explode(' OR ', $search);
            foreach ($searchToParse as $s) {
                //$where = array_merge($where, $this->stringToSearch($s), ['OR']);
                $where[] = $this->simpleStringToSearch($s);
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

    private function simpleStringToSearch(string $search): array
    {
        if ('children' == strtolower($search) && $this->apps->getCurrentPage()) {
            return ['parentPage', '=', $this->apps->getCurrentPage()->getId()];
        }

        if (0 === strpos($search, 'comment:')) {
            $search = '<!--'.substr($search, \strlen('comment:')).'-->';

            return ['key' => 'mainContent', 'operator' => 'LIKE', 'value' => '%'.$search.'%'];
        }

        if (0 === strpos($search, 'slug:')) {
            $search = substr($search, \strlen('slug:'));

            return ['key' => 'slug', 'operator' => 'LIKE', 'value' => $search];
        }

        return ['key' => 'mainContent', 'operator' => 'LIKE', 'value' => '%'.$search.'%'];
    }

    /**
     * @param int|array $max if max is int => max result,
     *                       if max is array => paginate where 0 => item per page and 1 (fac) maxPage
     */
    public function renderPagesList(
        Twig $twig,
        $search = '',
        $max = 0,
        $order = 'publishedAt,priority',
        string $view = '/component/pages_list.html.twig',
        $host = ''
    ): string {
        if ('card' == $view) {
            $view = '/component/pages_list_card.html.twig';
        }

        $search = \is_array($search) ? $search : $this->stringToSearch($search);

        $order = \is_string($order) ? ['key' => str_replace(['↑', '↓'], ['ASC', 'DESC'], $order)]
            : ['key' => $order[0], 'direction' => $order[1]];

        $queryBuilder = Repository::getPageRepository($this->em, $this->pageClass)
            ->getPublishedPageQueryBuilder(
                $host ?: [$this->getApp()->getMainHost(), ''],
                $search,
                $order,
                $this->getLimit($max)
            );

        if ($this->apps->getCurrentPage()) {
            $queryBuilder->andWhere('p.slug <> '.$this->apps->getCurrentPage()->getId());
        }

        if (\is_array($max) && isset($max[1]) && $max[1] > 1) {
            $pages = (array) $queryBuilder->getQuery()->getResult();
            $limit = $this->getLimit($max);
            if ($limit) {
                $pages = \array_slice($pages, 0, $limit);
            }

            $pager = (new Pagerfanta(new ArrayAdapter($pages)))
                ->setMaxNbPages($max[1] ?? 0)
                ->setMaxPerPage($max[0])
                ->setCurrentPage($this->getCurrentPage());
            $pages = $pager->getCurrentPageResults();
        } else {
            $pages = $queryBuilder->getQuery()->getResult();
        }

        $template = $this->getApp()->getView($view);

        return $twig->render($template, [
            'pager_route' => $this->getPagerRouteName(),
            'pager_route_params' => $this->getPagerRouteParams(),
            'pages' => $pages,
            'pager' => $pager ?? null,
        ]);
    }

    /**
     * @param string|array|null $viewName the view name
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

        return $pagerFantaTwigView->render($pagerfanta, $this->routeGeneratorFactory->create($options), $options)
            .($pagerfanta->hasNextPage() ? '<!-- pager:'.$pagerfanta->getNextPage().' -->' : '');
    }

    private function getLimit($max): int
    {
        return \is_int($max) ? $max : (\is_array($max) && isset($max[1]) ? $max[1] * $max[0] : 0);
    }

    private function getCurrentPage(): int
    {
        return (int) $this->requestStack->getCurrentRequest()->attributes->get('pager', 1);
    }
}
