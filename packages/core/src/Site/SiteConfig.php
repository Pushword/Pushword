<?php

namespace Pushword\Core\Site;

use Pushword\Core\Template\TemplateResolver;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class SiteConfig
{
    /** @var string[] */
    public private(set) array $hosts = [];

    public private(set) string $locale;

    /** @var string|string[]|null */
    private string|array|null $locales = null;

    public private(set) string $baseUrl;

    public private(set) string $name;

    public private(set) string $template;

    /** @var array<string, string|list<string>> filter chain per property label — a list of filter names/classes, or the same as a comma string (see ContentPipeline::getFilters()) */
    public array $filters = [];

    public private(set) bool $entityCanOverrideFilters;

    /** @var array<string, mixed> */
    private array $assets = [];

    /** @var array<(string|int), mixed> */
    private array $customProperties = [];

    private ?TemplateResolver $templateResolver = null;

    /**
     * True while this site is being frozen into a static export: templates then
     * skip everything that needs a live endpoint (admin toolbar, fragments…).
     */
    public private(set) bool $isStatic = false;

    /**
     * What resetStatic() restores. Stays false on a live kernel (worker-mode
     * safety); the static generator pins true on its render kernel — see setStatic().
     */
    private bool $isStaticAfterReset = false;

    public string $firstAppLocale = 'fr';

    /** @param array<string, mixed> $properties */
    public function __construct(
        private readonly ParameterBagInterface $params,
        array $properties,
        public readonly bool $isDefaultSite,
    ) {
        // Process custom_properties first (merge into the internal map)
        if (isset($properties['custom_properties']) && \is_array($properties['custom_properties'])) {
            foreach ($properties['custom_properties'] as $cpKey => $cpValue) {
                $this->customProperties[(string) $cpKey] = $cpValue;
            }
        }

        foreach ($properties as $prop => $value) {
            if ('custom_properties' === $prop) {
                continue; // Already processed above
            }

            $this->customProperties[$prop] = $value;

            $camelCase = static::normalizePropertyName($prop);
            if (property_exists($this, $camelCase)) {
                $this->$camelCase = $value; // @phpstan-ignore-line
            }
        }
    }

    private static function normalizePropertyName(string $string): string
    {
        if (! str_contains($string, '_')) {
            return $string; // already camelCase — lowercasing it would break property_exists (case-sensitive)
        }

        $string = str_replace('_', '', ucwords(strtolower($string), '_'));

        return lcfirst($string);
    }

    public function setTemplateResolver(TemplateResolver $templateResolver): void
    {
        $this->templateResolver = $templateResolver;
    }

    /**
     * With $pin, the value also becomes what resetStatic() restores. A plain set
     * does NOT survive rendering: Kernel::handle() marks services for reset, so
     * the NEXT handle() runs the services_resetter inside its boot(). Only the
     * static generator's render kernel — which never serves live traffic — pins
     * true; a kernel serving live traffic must never pin, or a worker keeps
     * believing it is exporting for every later request (see resetStatic()).
     */
    public function setStatic(bool $isStatic, bool $pin = false): self
    {
        $this->isStatic = $isStatic;
        if ($pin) {
            $this->isStaticAfterReset = $isStatic;
        }

        return $this;
    }

    /**
     * Worker-mode safety (kernel.reset, via SiteRegistry): a SiteConfig outlives
     * the request, so an in-process static generation must not leave the flag on.
     */
    public function resetStatic(): void
    {
        $this->isStatic = $this->isStaticAfterReset;
    }

    /** @return array{app_base_url: string, app_name: string, app_color: mixed, pwApp: self, isStatic: bool} */
    public function getParamsForRendering(): array
    {
        return [
            'app_base_url' => $this->baseUrl,
            'app_name' => $this->name,
            'app_color' => $this->getCustomProperty('color'),
            'pwApp' => $this,
            'isStatic' => $this->isStatic,
        ];
    }

    public function getMainHost(): string
    {
        if ([] === $this->hosts) {
            throw new \LogicException('No hosts defined for this site');
        }

        return $this->hosts[0];
    }

    public function has(string $key): bool
    {
        return null !== $this->get($key);
    }

    public function getStr(string $key, string $default = ''): string
    {
        $returnValue = $this->get($key) ?? $default;

        if (! \is_scalar($returnValue)) {
            throw new \LogicException('`'.$key.'` is not stringable');
        }

        return (string) $returnValue;
    }

    /**
     * @param array<array-key, mixed> $default
     *
     * @return array<array-key, mixed>
     */
    public function getArray(string $key, array $default = []): array
    {
        $returnValue = $this->get($key) ?? $default;

        if (! \is_array($returnValue)) {
            throw new \LogicException('`'.$key.'` is not an array');
        }

        return $returnValue;
    }

    /** @return string[] */
    public function getStringList(string $key): array
    {
        return array_map(
            static fn (mixed $v): string => \is_string($v) ? $v : throw new \InvalidArgumentException('`'.$key.'` contains non-string values'),
            $this->getArray($key),
        );
    }

    public function getBoolean(string $key, bool $default = true): bool
    {
        $returnValue = $this->get($key) ?? $default;

        if (! \is_bool($returnValue)) {
            throw new \LogicException('`'.$key.'` is not a boolean');
        }

        return $returnValue;
    }

    public function get(string $key): mixed
    {
        $camelCaseKey = static::normalizePropertyName($key);

        $method = 'get'.ucfirst($camelCaseKey);

        if (method_exists($this, $method)) {
            return $this->$method(); // @phpstan-ignore-line
        }

        if (property_exists($this, $camelCaseKey) && isset($this->$camelCaseKey)) { // @phpstan-ignore-line
            return $this->$camelCaseKey; // @phpstan-ignore-line
        }

        return $this->getCustomProperty($key);
    }

    public function setCustomProperty(string $key, mixed $value): self
    {
        $camelCaseKey = static::normalizePropertyName($key);
        if (property_exists($this, $camelCaseKey)) {
            $this->$camelCaseKey = $value; // @phpstan-ignore-line
        }

        $this->customProperties[$key] = $value;

        return $this;
    }

    public function getCustomProperty(string $key): mixed
    {
        return $this->customProperties[$key] ?? null;
    }

    /** @return string[] */
    public function getJavascripts(): array
    {
        /** @var string[] */
        return $this->assets['javascripts'] ?? [];
    }

    /** @return string[] */
    public function getViteStylesheets(): array
    {
        /** @var string[] */
        return $this->assets['vite_stylesheets'] ?? [];
    }

    /** @return string[] */
    public function getViteJavascripts(): array
    {
        /** @var string[] */
        return $this->assets['vite_javascripts'] ?? [];
    }

    /** @return string[] */
    public function getStylesheets(): array
    {
        /** @var string[] */
        return $this->assets['stylesheets'] ?? [];
    }

    public function getView(?string $path = null, string $fallback = '@Pushword'): string
    {
        if (null === $this->templateResolver) {
            throw new \LogicException('TemplateResolver not set. Call setTemplateResolver() first.');
        }

        return $this->templateResolver->resolve($this, $path, $fallback);
    }

    /** @return string[]|string */
    public function getHostForDoctrineSearch(): array|string
    {
        return $this->isDefaultSite ? ['', $this->getMainHost()] : $this->getMainHost();
    }

    public function getDefaultLocale(): string
    {
        $defaultLocale = $this->getCustomProperty('defaultLocale') ?? $this->firstAppLocale;

        assert(\is_string($defaultLocale));

        return $defaultLocale;
    }

    /** @return string[] */
    public function getLocales(): array
    {
        if (\is_string($this->locales)) {
            $this->locales = explode('|', $this->locales);
        }

        return $this->locales ?? throw new \Exception();
    }
}
