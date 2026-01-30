<?php

namespace Pushword\Core\Site;

use Pushword\Core\Template\TemplateResolver;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class SiteConfig
{
    /** @var string[] */
    private array $hosts;

    private string $locale;

    /** @var string|string[]|null */
    private string|array|null $locales = null;

    private string $baseUrl;

    private string $name;

    private string $template;

    /** @var array<string, string> */
    private array $filters = [];

    private bool $entityCanOverrideFilters;

    /** @var array<string, mixed> */
    private array $assets = [];

    /** @var array<(string|int), mixed> */
    private array $customProperties = [];

    private ?TemplateResolver $templateResolver = null;

    public string $firstAppLocale = 'fr';

    /** @param array<string, mixed> $properties */
    public function __construct(
        private readonly ParameterBagInterface $params,
        array $properties,
        private readonly bool $isDefaultSite,
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
        $string = str_replace('_', '', ucwords(strtolower($string), '_'));

        return lcfirst($string);
    }

    public function setTemplateResolver(TemplateResolver $templateResolver): void
    {
        $this->templateResolver = $templateResolver;
    }

    /** @return array{app_base_url: string, app_name: string, app_color: mixed, pwApp: self} */
    public function getParamsForRendering(): array
    {
        return [
            'app_base_url' => $this->getBaseUrl(),
            'app_name' => $this->name,
            'app_color' => $this->getCustomProperty('color'),
            'pwApp' => $this,
        ];
    }

    public function getMainHost(): string
    {
        if ([] === $this->hosts) {
            throw new \LogicException('No hosts defined for this site');
        }

        return $this->hosts[0];
    }

    /** @return string[] */
    public function getHosts(): array
    {
        return $this->hosts;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
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

    public function getTemplate(): string
    {
        return $this->template;
    }

    /** @return array<string, string> */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /** @param array<string, string> $filters */
    public function setFilters(array $filters): void
    {
        $this->filters = $filters;
    }

    public function entityCanOverrideFilters(): bool
    {
        return $this->entityCanOverrideFilters;
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

    public function isDefaultSite(): bool
    {
        return $this->isDefaultSite;
    }

    /** @return string[]|string */
    public function getHostForDoctrineSearch(): array|string
    {
        return $this->isDefaultSite ? ['', $this->getMainHost()] : $this->getMainHost();
    }

    public function getLocale(): string
    {
        return $this->locale;
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

    public function getName(): string
    {
        return $this->name;
    }
}
