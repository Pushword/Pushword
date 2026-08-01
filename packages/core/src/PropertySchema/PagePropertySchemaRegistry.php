<?php

namespace Pushword\Core\PropertySchema;

use Pushword\Core\Site\SiteRegistry;

/**
 * Resolves the declared page-property schema for a host. Sources, later wins,
 * whole-descriptor replacement (never a deep merge): bundle providers, then
 * root `page_properties` config, then the app's own (the config levels are
 * already merged into the app by PushwordConfigFactory). A null descriptor
 * (`name: ~`) un-declares the property for that host.
 */
final class PagePropertySchemaRegistry
{
    /** @var array<string, array<string, PagePropertySchema>> */
    private array $cache = [];

    /** @param iterable<PagePropertiesProviderInterface> $providers */
    public function __construct(
        private readonly SiteRegistry $apps,
        private readonly iterable $providers,
    ) {
    }

    /** @return array<string, PagePropertySchema> keyed by property name */
    public function for(?string $host = null): array
    {
        $site = $this->apps->get($host);
        $mainHost = $site->getMainHost();

        if (isset($this->cache[$mainHost])) {
            return $this->cache[$mainHost];
        }

        $declared = [];
        foreach ($this->providers as $provider) {
            $declared = [...$declared, ...$provider->getPageProperties()];
        }

        $declared = [...$declared, ...$site->getArray('page_properties')];

        $schemas = [];
        foreach ($declared as $name => $descriptor) {
            if (null === $descriptor) {
                continue; // `name: ~` un-declares for this host
            }

            $schemas[(string) $name] = PagePropertySchemaFactory::fromConfig((string) $name, $descriptor);
        }

        return $this->cache[$mainHost] = $schemas;
    }

    public function schema(?string $host, string $name): ?PagePropertySchema
    {
        return $this->for($host)[$name] ?? null;
    }
}
