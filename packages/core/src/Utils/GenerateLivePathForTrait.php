<?php

namespace Pushword\Core\Utils;

use Pushword\Core\Entity\Page;
use Pushword\Core\Router\PushwordRouteGenerator;

trait GenerateLivePathForTrait
{
    protected PushwordRouteGenerator $router;

    /**
     * @param array<string, string> $params
     */
    public function generateLivePathFor(Page|string $host, string $route = 'pushword_page', array $params = []): string
    {
        if (isset($params['locale'])) {
            $params['_locale'] = $params['locale'].'/';
            unset($params['locale']);
        }

        if ($host instanceof Page) {
            $page = $host;
            $host = $page->getHost();
            $params['slug'] = $page->getRealSlug();
        }

        if ('' !== $host) {
            $params['host'] = $host;
            $route = 'custom_host_'.$route;
        }

        return $this->router->getRouter()->generate($route, $params);
    }
}
