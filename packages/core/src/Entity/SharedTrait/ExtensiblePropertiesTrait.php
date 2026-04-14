<?php

namespace Pushword\Core\Entity\SharedTrait;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

trait ExtensiblePropertiesTrait
{
    /** @var array<mixed> */
    #[ORM\Column(type: Types::JSON, options: ['default' => '{}'])]
    protected array $customProperties = [];

    protected string $unmanagedPropertiesYaml = '';

    #[Ignore]
    protected string $buildValidationAtPath = 'unmanagedPropertiesAsYaml';

    /** @return array<mixed> */
    public function getCustomProperties(): array
    {
        return $this->customProperties;
    }

    /** @param array<mixed> $customProperties */
    public function setCustomProperties(array $customProperties): self
    {
        $this->customProperties = $customProperties;

        return $this;
    }

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
            fn (string $key): bool => ! $this->isManagedProperty($key),
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

    protected function mergeUnmanagedProperties(): void
    {
        $unmanagedProperties = '' !== $this->unmanagedPropertiesYaml
            ? Yaml::parse($this->unmanagedPropertiesYaml)
            : [];

        if (! \is_array($unmanagedProperties)) {
            throw new InvalidArgumentException('Unmanaged properties are not a valid YAML array');
        }

        $this->unmanagedPropertiesYaml = '';

        // Remove unmanaged properties that were deleted from the YAML
        foreach (array_keys($this->customProperties) as $existingKey) {
            if ($this->isManagedProperty((string) $existingKey)) {
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
                throw new InvalidArgumentException('Property `'.(string) $name.'` is managed by a dedicated field and cannot be set via unmanaged properties');
            }

            $this->setCustomProperty((string) $name, $value);
        }
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
            $executionContext->buildViolation('page.customProperties.notStandAlone')
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
     * @param mixed[] $arguments
     */
    public function __call(string $method, array $arguments = []): mixed
    {
        if ('_actions' === $method) {
            return null;
        }

        if (str_starts_with($method, 'get')) {
            $property = lcfirst(substr($method, 3));

            return $this->getCustomProperty($property);
        }

        return $this->getCustomProperty(lcfirst($method));
    }
}
