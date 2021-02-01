<?php

namespace Pushword\Core\Twig;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Pagerfanta\PagerfantaInterface;
use Pagerfanta\RouteGenerator\RouteGeneratorFactoryInterface;
use Pagerfanta\Twig\View\TwigView;
use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\PageInterface;
use Pushword\Core\Repository\Repository;
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

    /**
     * @param int|array $max if max is int => max result,
     *                       if max is array => paginate where 0 => item per page and 1 (fac) maxPage
     */
    public function renderChildrenListCard(Twig $twig, PageInterface $page, $max = 0): string
    {
        return $this->renderChildrenList($twig, $page, $max, '/component/pages_list_card.html.twig');
    }

    /**
     * @param int|array $max if max is int => max result,
     *                       if max is array => paginate where 0 => item per page and 1 (fac) maxPage
     */
    public function renderChildrenList(
        Twig $twig,
        PageInterface $page,
        $max = 0,
        string $view = '/component/pages_list.html.twig'
    ): string {
        $pages = $page->getChildrenPages();

        $limit = $this->getLimit($max);
        if ($limit) {
            $pages = $pages->slice(0, $limit);
        }

        if (\is_array($max)) {
            $pager = (new Pagerfanta(new ArrayAdapter($pages instanceof ArrayCollection ? $pages->toArray() : $pages)))
                ->setMaxPerPage($max[0])
                ->setCurrentPage($this->getCurrentPage());
            $pages = $pager->getCurrentPageResults();
        }

        $template = $this->getApp()->getView($view);

        return $twig->render($template, [
            'pager_route' => $this->getPagerRouteName(),
            'pager_route_params' => $this->getPagerRouteParams(),
            'pages' => $pages,
            'pager' => $pager ?? null,
        ]);
    }

    private function getPagerRouteParams(): array
    {
        $params = [];
        if ($this->requestStack->getCurrentRequest()->get('slug')) {
            $params['slug'] = rtrim($this->requestStack->getCurrentRequest()->get('slug'), '/');
        }

        if ($this->requestStack->getCurrentRequest()->get('host')) {
            $params['host'] = $this->requestStack->getCurrentRequest()->get('host');
        }

        return $params;
    }

    private function getPagerRouteName(): string
    {
        $pagerRouter = $this->requestStack->getCurrentRequest()->get('_route');
        $pagerRouter .= '' === $this->requestStack->getCurrentRequest()->get('slug') ? '_homepage' : '';
        $pagerRouter .= '_pager';

        return $pagerRouter;
    }

    public function renderPagesListCard(
        Twig $twig,
        $search = '',
        $max = 0,
        $order = 'createdAt',
        $host = ''
    ): string {
        return $this->renderPagesList($twig, $search, $max, $order, $host, '/component/pages_list_card.html.twig');
    }

    /**
     * @param int|array $max if max is int => max result,
     *                       if max is array => paginate where 0 => item per page and 1 (fac) maxPage
     */
    public function renderPagesList(
        Twig $twig,
        $search = '',
        $max = 0,
        $order = 'createdAt',
        $host = '',
        string $view = '/component/pages_list.html.twig'
    ): string {
        if (\is_string($search)) {
            $search = [['key' => 'mainContent', 'operator' => 'LIKE', 'value' => '%'.$search.'%']];
        }
        if ($this->apps->getCurrentPage()) {
            $search[] = ['key' => 'id', 'operator' => '!=', 'value' => $this->apps->getCurrentPage()->getId()];
        }

        $order = \is_string($order) ? ['key' => $order, 'direction' => 'DESC']
            : ['key' => $order[0], 'direction' => $order[1]];

        $queryBuilder = Repository::getPageRepository($this->em, $this->pageClass)
            ->getPublishedPageQueryBuilder(
                $host ?: $this->getApp()->getMainHost(),
                $search,
                $order,
                $this->getLimit($max)
            );

        if (\is_array($max)) {
            $pages = (array) $queryBuilder->getQuery()->getResult();
            $limit = $this->getLimit($max);
            if ($limit) {
                $pages = \array_slice($pages, 0, $limit);
            }

            $pager = (new Pagerfanta(new ArrayAdapter($pages)))
            // Wait PR https://github.com/BabDev/Pagerfanta/pull/21 to be merged and released
            // $pager = (new Pagerfanta(new QueryAdapter($queryBuilder)))
                // ->setMaxNbPages($max[1] ?? 0)
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
