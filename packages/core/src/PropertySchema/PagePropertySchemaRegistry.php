<?php

namespace Pushword\Core\PropertySchema;

use Pushword\Core\Entity\Page;
use Pushword\Core\Site\SiteConfig;
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

        $schemas = [];
        foreach ($this->describeSite($site) as $name => $descriptor) {
            $schemas[$name] = PagePropertySchemaFactory::fromConfig($name, $descriptor);
        }

        return $this->cache[$mainHost] = $schemas;
    }

    /**
     * The merged raw descriptors for a host, in the `page_properties` config
     * shape — what an author would write, for `pw:schema:dump` and /api/docs.
     *
     * @return array<string, array<string, mixed>>
     */
    public function describe(?string $host = null): array
    {
        return $this->describeSite($this->apps->get($host));
    }

    /** @return array<string, array<string, mixed>> */
    private function describeSite(SiteConfig $site): array
    {
        $declared = [];
        foreach ($this->providers as $provider) {
            $declared = [...$declared, ...$provider->getPageProperties()];
        }

        $declared = [...$declared, ...$site->getArray('page_properties')];

        foreach ($declared as $name => $descriptor) {
            if (null === $descriptor) {
                unset($declared[$name]); // `name: ~` un-declares for this host
            }
        }

        /** @var array<string, array<string, mixed>> $declared */
        return $declared;
    }

    public function schema(?string $host, string $name): ?PagePropertySchema
    {
        return $this->for($host)[$name] ?? null;
    }

    /**
     * The informational findings for one page: custom property keys the host's
     * schema does not know (the near-miss net that catches a `toc_title` typo'd
     * for `tocTitle`) and declared-required keys the page lacks. Shared by the
     * flat import summary and the API write warnings; never blocks anything.
     *
     * @return array{undeclared: list<string>, missingRequired: list<string>}
     */
    public function complianceFor(Page $page): array
    {
        $schemas = $this->for('' !== $page->host ? $page->host : null);

        $undeclared = [];
        foreach (array_keys($page->customProperties) as $key) {
            if (isset($schemas[(string) $key])) {
                continue;
            }

            if ($page->isManagedProperty((string) $key)) {
                continue;
            }

            $undeclared[] = (string) $key;
        }

        $missingRequired = [];
        foreach ($schemas as $name => $schema) {
            if ($schema->required && ! $page->hasCustomProperty($name)) {
                $missingRequired[] = $name;
            }
        }

        return ['undeclared' => $undeclared, 'missingRequired' => $missingRequired];
    }
}
