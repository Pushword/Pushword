<?php

namespace Pushword\Core\Utils;

use Pushword\Core\Entity\PageInterface;
use Pushword\Core\Router\RouterInterface;

trait GenerateLivePathForTrait
{
    protected RouterInterface $router;

    /**
     * @param string|PageInterface  $host
     * @param array<string, string> $params
     */
    public function generateLivePathFor($host, string $route = 'pushword_page', array $params = []): string
    {
        if (isset($params['locale'])) {
            $params['_locale'] = $params['locale'].'/';
            unset($params['locale']);
        }

        if ($host instanceof PageInterface) {
            $page = $host;
            $host = $page->getHost();
        }

        if (isset($page)) {
            $params['slug'] = $page->getRealSlug();
        }

        if ('' !== $host) {
            $params['host'] = $host;
            $route = 'custom_host_'.$route;
        }

        return $this->router->getRouter()->generate($route, $params);
    }
}
