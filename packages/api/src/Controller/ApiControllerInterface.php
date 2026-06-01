<?php

namespace Pushword\Api\Controller;

/**
 * Marker interface for API controllers.
 *
 * Implementing controllers are auto-tagged `pushword.api.controller` and
 * contribute their OpenAPI fragment via a static `describe()` method.
 *
 * @phpstan-type OpenApiFragment array{
 *   paths?: array<string, array<string, mixed>>,
 *   components?: array{schemas?: array<string, array<string, mixed>>}
 * }
 */
interface ApiControllerInterface
{
    /**
     * OpenAPI fragment contributed by this controller.
     *
     * Returned shape is merged into the bundle's `/api/docs` document.
     *
     * @return array<string, mixed>
     */
    public static function describe(): array;
}
