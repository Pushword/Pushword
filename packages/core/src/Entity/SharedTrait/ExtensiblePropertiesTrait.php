<?php

namespace Pushword\Core\Entity\SharedTrait;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use InvalidArgumentException;
use LogicException;
use Pushword\Core\Utils\Entity;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

trait ExtensiblePropertiesTrait
{
    /** @var array<mixed> */
    #[ORM\Column(type: Types::JSON, options: ['default' => '{}'])]
    public array $customProperties = [];

    protected string $unmanagedPropertiesYaml = '';

    /**
     * True once the YAML editing surface (admin textarea) has fed a value.
     * Guards the destructive reconciliation so callers that never expose the
     * textarea (e.g. the API) cannot wipe unmanaged properties on validation.
     */
    #[Ignore]
    protected bool $unmanagedPropertiesYamlProvided = false;

    #[Ignore]
    protected string $buildValidationAtPath = 'unmanagedPropertiesAsYaml';

    /**
     * Return custom properties without the ones which have a dedicated getter/setter.
     * Override getManagedPropertyKeys() to declare which keys are managed.
     */
    public function getUnmanagedPropertiesAsYaml(): string
    {
        if ([] === $this->customProperties) {
            return '';
        }

        $unmanagedProperties = array_filter(
            $this->customProperties,
            fn (string $key): bool => ! $this->isManagedProperty($key) && ! $this->isSchemaProperty($key),
            \ARRAY_FILTER_USE_KEY,
        );

        return [] === $unmanagedProperties ? '' : Yaml::dump($unmanagedProperties);
    }

    public function setUnmanagedPropertiesAsYaml(?string $yaml): self
    {
        return $this->setUnmanagedPropertiesFromYaml($yaml);
    }

    public function setUnmanagedPropertiesFromYaml(?string $yaml, bool $merge = false): self
    {
        $this->unmanagedPropertiesYaml = (string) $yaml;
        $this->unmanagedPropertiesYamlProvided = true;

        if ($merge) {
            $this->mergeUnmanagedProperties();
        }

        return $this;
    }

    /**
     * Runtime-registered managed property keys (from admin form fields).
     *
     * @var array<string, true>
     */
    #[Ignore]
    protected array $runtimeManagedKeys = [];

    /**
     * Keys shielded from the destructive textarea reconciliation without being
     * owned by a field: the API registers everything it writes here so a later
     * validation merge cannot wipe it. Preserved keys stay visible and editable
     * in the textarea.
     *
     * @var array<string, true>
     */
    #[Ignore]
    protected array $preservedKeys = [];

    /**
     * Schema-declared keys rendered by a generated admin field. Filtered from
     * the textarea like managed keys, but a textarea that still carries one —
     * a form opened before the key's field existed — writes through instead of
     * throwing. Permanent rule, not a migration workaround: the stale-form
     * window reopens every time any site declares a new property.
     *
     * @var array<string, true>
     */
    #[Ignore]
    protected array $schemaKeys = [];

    /**
     * Declare which custom property keys are managed by dedicated form fields.
     * Override in entity to add keys.
     *
     * @return string[]
     */
    public function getManagedPropertyKeys(): array
    {
        return array_keys($this->runtimeManagedKeys);
    }

    /**
     * Register a managed property key at runtime (for admin form fields).
     */
    public function registerManagedPropertyKey(string $name): self
    {
        $this->runtimeManagedKeys[strtolower($name)] = true;

        return $this;
    }

    public function isManagedProperty(string $name): bool
    {
        return \in_array(strtolower($name), array_map(strtolower(...), $this->getManagedPropertyKeys()), true);
    }

    /**
     * Shield a key from the destructive textarea reconciliation ("don't wipe
     * this"), without a field owning it.
     */
    public function preserveCustomProperty(string $name): self
    {
        $this->preservedKeys[strtolower($name)] = true;

        return $this;
    }

    public function isPreservedProperty(string $name): bool
    {
        return isset($this->preservedKeys[strtolower($name)]);
    }

    /**
     * Register a schema-declared key rendered by a generated admin field.
     */
    public function registerSchemaPropertyKey(string $name): self
    {
        $this->schemaKeys[strtolower($name)] = true;

        return $this;
    }

    public function isSchemaProperty(string $name): bool
    {
        return isset($this->schemaKeys[strtolower($name)]);
    }

    #[Ignore]
    public function getValidationAtPath(): string
    {
        return $this->buildValidationAtPath;
    }

    /**
     * The custom properties as they will read once the pending textarea YAML
     * merges — a read-only preview for validators, which run BEFORE the
     * `#[Assert\Callback]` performs the real merge. Malformed YAML and
     * managed-key conflicts are ignored here: the merge raises them itself.
     *
     * @return array<mixed>
     */
    #[Ignore]
    public function getEffectiveCustomProperties(): array
    {
        if (! $this->unmanagedPropertiesYamlProvided) {
            return $this->customProperties;
        }

        try {
            $unmanagedProperties = '' !== $this->unmanagedPropertiesYaml
                ? Yaml::parse($this->unmanagedPropertiesYaml)
                : [];
        } catch (ParseException) {
            return $this->customProperties;
        }

        if (! \is_array($unmanagedProperties)) {
            return $this->customProperties;
        }

        $effective = $this->customProperties;
        foreach (array_keys($effective) as $existingKey) {
            if ($this->isShieldedFromReconciliation((string) $existingKey)) {
                continue;
            }

            if (isset($unmanagedProperties[(string) $existingKey])) {
                continue;
            }

            unset($effective[$existingKey]);
        }

        foreach ($unmanagedProperties as $name => $value) {
            if ($this->isManagedProperty((string) $name)) {
                continue;
            }

            $effective[$name] = $value;
        }

        return $effective;
    }

    protected function mergeUnmanagedProperties(): void
    {
        // Only reconcile when the YAML surface actually fed a value; otherwise
        // an empty transient YAML would silently drop every unmanaged property.
        if (! $this->unmanagedPropertiesYamlProvided) {
            return;
        }

        $unmanagedProperties = '' !== $this->unmanagedPropertiesYaml
            ? Yaml::parse($this->unmanagedPropertiesYaml)
            : [];

        if (! \is_array($unmanagedProperties)) {
            throw new InvalidArgumentException('Unmanaged properties are not a valid YAML array');
        }

        $this->unmanagedPropertiesYaml = '';
        $this->unmanagedPropertiesYamlProvided = false;

        // Remove unmanaged properties that were deleted from the YAML
        foreach (array_keys($this->customProperties) as $existingKey) {
            if ($this->isShieldedFromReconciliation((string) $existingKey)) {
                continue;
            }

            if (isset($unmanagedProperties[(string) $existingKey])) {
                continue;
            }

            $this->removeCustomProperty((string) $existingKey);
        }

        if ([] === $unmanagedProperties) {
            return;
        }

        foreach ($unmanagedProperties as $name => $value) {
            if ($this->isManagedProperty((string) $name)) {
                throw new InvalidArgumentException('Property `'.$name.'` is managed by a dedicated field and cannot be set via unmanaged properties');
            }

            // A schema key found here comes from a form rendered before its
            // field existed — the typed value wins over the stale stored one;
            // the field writeback overwrites later when the payload carries it.
            $this->setCustomProperty((string) $name, $value);
        }
    }

    private function isShieldedFromReconciliation(string $key): bool
    {
        if ($this->isManagedProperty($key)) {
            return true;
        }

        if ($this->isPreservedProperty($key)) {
            return true;
        }

        return $this->isSchemaProperty($key);
    }

    #[Assert\Callback]
    public function validateUnmanagedProperties(ExecutionContextInterface $executionContext): void
    {
        try {
            $this->mergeUnmanagedProperties();
        } catch (ParseException) {
            $executionContext->buildViolation('pageCustomPropertiesMalformed')
                ->atPath($this->buildValidationAtPath)
                ->addViolation();
        } catch (InvalidArgumentException) {
            $executionContext->buildViolation('pageCustomPropertiesNotStandAlone')
                ->atPath($this->buildValidationAtPath)
                ->addViolation();
        }
    }

    public function setCustomProperty(string $name, mixed $value): void
    {
        $this->customProperties[$name] = $value;
    }

    public function hasCustomProperty(string $name): bool
    {
        return isset($this->customProperties[$name]);
    }

    public function getCustomProperty(string $name): mixed
    {
        return $this->customProperties[$name] ?? null;
    }

    public function getCustomPropertyScalar(string $name): bool|float|int|string|null
    {
        $return = $this->customProperties[$name] ?? null;
        if (null !== $return && ! \is_scalar($return)) {
            throw new LogicException(\gettype($return));
        }

        return $return;
    }

    /** @return array<string> */
    public function getCustomPropertyList(string $name): array
    {
        $value = $this->customProperties[$name] ?? null;

        if (! \is_array($value)) {
            throw new LogicException(\gettype($value));
        }

        $toReturn = [];
        foreach ($value as $v) {
            $toReturn[] = \is_string($v) ? $v : throw new Exception();
        }

        return $toReturn;
    }

    public function removeCustomProperty(string $name): void
    {
        unset($this->customProperties[$name]);
    }

    /**
     * Minimal __call for Twig ergonomics: page.someKey delegates to getCustomProperty().
     *
     * A getter dropped in favour of a hooked property is answered by that property:
     * a template still calling `page.getTitle()` would otherwise land on the custom
     * property `title`, which nobody set — rendering empty rather than failing.
     *
     * @param mixed[] $arguments
     */
    public function __call(string $method, array $arguments = []): mixed
    {
        if ('_actions' === $method) {
            return null;
        }

        $property = str_starts_with($method, 'get') ? lcfirst(substr($method, 3)) : lcfirst($method);

        if (Entity::isPubliclyReadableProperty($this, $property)) {
            return $this->{$property}; // @phpstan-ignore property.dynamicName
        }

        return $this->getCustomProperty($property);
    }
}
