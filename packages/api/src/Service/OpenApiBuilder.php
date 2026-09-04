<?php

namespace Pushword\Api\Service;

use Pushword\Api\Controller\ApiControllerInterface;
use Pushword\Core\PropertySchema\PagePropertySchemaRegistry;
use Pushword\Core\Site\SiteRegistry;

/**
 * Assembles the /api/docs OpenAPI document from each controller's `describe()`
 * fragment. Controllers are discovered via the `pushword.api.controller` DI tag
 * (auto-applied because `AbstractApiController` implements
 * `ApiControllerInterface`).
 */
final readonly class OpenApiBuilder
{
    /**
     * @param iterable<ApiControllerInterface> $controllers
     */
    public function __construct(
        private iterable $controllers,
        private PagePropertySchemaRegistry $pageProperties,
        private SiteRegistry $apps,
        private string $title = 'Pushword API',
        private string $version = '1.0.0',
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        /** @var array<string, array<string, mixed>> $paths */
        $paths = [];
        /** @var array<string, array<string, mixed>> $schemas */
        $schemas = [];
        /** @var array<string, array<string, mixed>> $securitySchemes */
        $securitySchemes = [];

        foreach ($this->controllers as $controller) {
            $fragment = $controller::describe();

            if (isset($fragment['paths']) && \is_array($fragment['paths'])) {
                foreach ($fragment['paths'] as $path => $operations) {
                    if (! \is_string($path)) {
                        continue;
                    }

                    if (! \is_array($operations)) {
                        continue;
                    }

                    /** @var array<string, mixed> $existing */
                    $existing = $paths[$path] ?? [];
                    /** @var array<string, mixed> $operations */
                    $paths[$path] = array_merge($existing, $operations);
                }
            }

            $components = $fragment['components'] ?? null;
            if (! \is_array($components)) {
                continue;
            }

            if (isset($components['schemas']) && \is_array($components['schemas'])) {
                foreach ($components['schemas'] as $name => $schema) {
                    if (! \is_string($name)) {
                        continue;
                    }

                    if (! \is_array($schema)) {
                        continue;
                    }

                    /** @var array<string, mixed> $schema */
                    $schemas[$name] = $schema;
                }
            }

            if (isset($components['securitySchemes']) && \is_array($components['securitySchemes'])) {
                foreach ($components['securitySchemes'] as $name => $securityScheme) {
                    if (\is_string($name) && \is_array($securityScheme)) {
                        $securitySchemes[$name] = $securityScheme;
                    }
                }
            }
        }

        ksort($paths);
        ksort($schemas);
        ksort($securitySchemes);

        // Vendor extension: the per-host declared page properties, in the
        // `page_properties` config shape, so an agent reading /api/docs knows
        // which frontmatter keys are valid before writing a page.
        $pagePropertiesPerHost = [];
        foreach ($this->apps->getHosts() as $host) {
            $described = $this->pageProperties->describe($host);
            if ([] !== $described) {
                $pagePropertiesPerHost[$host] = $described;
            }
        }

        return [
            'x-pushword-page-properties' => $pagePropertiesPerHost,
            'openapi' => '3.1.0',
            'info' => [
                'title' => $this->title,
                'version' => $this->version,
                'description' => 'Editor-facing REST API mirror of the Pushword admin. Authenticate by sending `Authorization: Bearer <user.apiToken>` (issue tokens via `bin/console pw:user:token <email>`).',
            ],
            'security' => [['bearerAuth' => []]],
            'paths' => $paths,
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => ['type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'opaque'],
                    ...$securitySchemes,
                ],
                'schemas' => $schemas,
            ],
        ];
    }
}
