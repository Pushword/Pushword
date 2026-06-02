<?php

namespace Pushword\Api\Routing;

use Pushword\Api\Controller\ApiControllerInterface;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\RouteCollection;

/**
 * Loads the #[Route] attributes of every controller tagged
 * `pushword.api.controller` — api's own controllers plus those contributed by
 * optional bundles (conversation, flat, snippet).
 *
 * Routing all API endpoints through this single loader means optional packages
 * never register their own API routes: they cannot be half-wired or forgotten,
 * and when pushword/api is absent this loader does not exist, so none of those
 * routes are declared (no class is force-loaded either).
 *
 * Enabled with one import — shipped by this bundle, nothing to wire per package:
 *
 *     pushword_api:
 *         resource: .
 *         type: pushword_api
 */
final class ApiControllerRouteLoader extends Loader
{
    /** @param iterable<ApiControllerInterface> $controllers */
    public function __construct(private readonly iterable $controllers)
    {
        parent::__construct();
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        $collection = new RouteCollection();
        $seen = [];
        foreach ($this->controllers as $controller) {
            $class = $controller::class;
            if (isset($seen[$class])) {
                continue;
            }

            $seen[$class] = true;
            $imported = $this->import($class, 'attribute');
            if ($imported instanceof RouteCollection) {
                $collection->addCollection($imported);
            }
        }

        return $collection;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return 'pushword_api' === $type;
    }
}
