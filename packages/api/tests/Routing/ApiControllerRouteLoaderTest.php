<?php

namespace Pushword\Api\Tests\Routing;

use PHPUnit\Framework\TestCase;
use Pushword\Api\Controller\ApiControllerInterface;
use Pushword\Api\Routing\ApiControllerRouteLoader;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class ApiControllerRouteLoaderTest extends TestCase
{
    public function testSupportsOnlyPushwordApiType(): void
    {
        $loader = new ApiControllerRouteLoader([]);

        self::assertTrue($loader->supports('.', 'pushword_api'));
        self::assertFalse($loader->supports('.', 'attribute'));
        self::assertFalse($loader->supports('.', null));
    }

    public function testLoadsEachControllerClassOnceEvenWhenRepeated(): void
    {
        // Two instances of the same controller class plus one of another class.
        $controller = new class implements ApiControllerInterface {
            public static function describe(): array
            {
                return [];
            }
        };
        $sameClass = clone $controller;
        $otherController = new class implements ApiControllerInterface {
            public static function describe(): array
            {
                return [];
            }
        };

        $attributeLoader = new RecordingAttributeLoader();

        $loader = new ApiControllerRouteLoader([$controller, $sameClass, $otherController]);
        $loader->setResolver(new LoaderResolver([$attributeLoader]));

        $collection = $loader->load('.', 'pushword_api');

        // The duplicated class is imported only once; both distinct classes contribute.
        self::assertSame([$controller::class, $otherController::class], $attributeLoader->imported);
        self::assertCount(2, $collection);
    }

    public function testLoadReturnsEmptyCollectionWithoutControllers(): void
    {
        $loader = new ApiControllerRouteLoader([]);
        $loader->setResolver(new LoaderResolver([new RecordingAttributeLoader()]));

        self::assertCount(0, $loader->load('.', 'pushword_api'));
    }
}

/**
 * Stub attribute loader: records every imported resource and returns a
 * one-route collection with a unique name so distinct imports accumulate.
 */
final class RecordingAttributeLoader extends Loader
{
    /** @var list<mixed> */
    public array $imported = [];

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        $this->imported[] = $resource;

        $collection = new RouteCollection();
        $collection->add('route_'.\count($this->imported), new Route('/'));

        return $collection;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return 'attribute' === $type;
    }
}
