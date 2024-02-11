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
use Pushword\Core\Entity\PageInterface;
use Pushword\Core\Repository\Repository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\Attribute\Required;
use Twig\Environment as Twig;

/**
 * TODO documenter
 * Possible value :
 * - children
 * - parent_children
 * - related
 * - related:comment:blog
 * - comment:blog
 * Operators : OR
 * Example :
 * [✔] related:comment:blog OR related
 * [✗] children AND slug:homepage.
 */
trait PageListTwigTrait
{
    /**
     * @var class-string<PageInterface>
     */
    private string $pageClass;

    private EntityManagerInterface $em;

    private AppPool $apps;

    #[Required]
    public RequestStack $requestStack;

    abstract public function getApp(): AppConfig;

    #[Required]
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
            $params['host'] = (string) $host;
        }

        return $params;
    }

    private function getPagerRouteName(): string
    {
        $pagerRouter = null !== $this->getCurrentRequest() ? $this->getCurrentRequest()->attributes->get('_route') : 'pushword_page';
        $pagerRouter .= null !== $this->getCurrentRequest() && '' === $this->getCurrentRequest()->attributes->get('slug') ? '_homepage' : '';

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
        Twig $twig,
        array|string $search = '',
        array|int $max = 0,
        array|string $order = 'publishedAt,priority',
        string $view = '',
        array|string $host = '',
        ?PageInterface $currentPage = null
    ): string {
        $currentPage ??= $this->apps->getCurrentPage(); // todo : drop app's current page

        $view = 'card' == $view ?
                '/component/pages_list_card.html.twig'
                : (\in_array($view, ['', 'list'], true) ? '/component/pages_list.html.twig' : $view);

        $search = \is_array($search) ? $search : (new StringToSearch($search, $currentPage))->retrieve();

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

        if (null !== $currentPage) {
            $queryBuilder->andWhere('p.id <> '.($currentPage->getId() ?? 0));
        }

        if (\is_array($max) && isset($max[1]) && $max[1] > 1) {
            $pages = (array) $queryBuilder->getQuery()->getResult();
            $limit = $this->getLimit($max);
            if (0 !== $limit) {
                $pages = \array_slice($pages, 0, $limit);
            }

            if ($max[0] < 1) {
                throw new \LogicException();
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

        return \intval($this->requestStack->getCurrentRequest()->attributes->getInt('pager', 1));
    }
}
