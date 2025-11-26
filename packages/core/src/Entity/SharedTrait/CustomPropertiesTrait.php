<?php

namespace Pushword\Core\Entity\SharedTrait;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use LogicException;

use function Safe\preg_match;

use Symfony\Component\Serializer\Annotation\Ignore;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

trait CustomPropertiesTrait
{
    /**
     * YAML Format.
     *
     * @var array<mixed>
     */
    #[ORM\Column(type: Types::JSON)]
    protected array $customProperties = [];

    /**
     * Stand Alone for not indexed
     * Yaml format, use only for setting, else get always retrieve standAlone from $customProperties.
     */
    protected string $standAloneCustomProperties = '';

    #[Ignore]
    protected string $buildValidationAtPath = 'standAloneCustomProperties';

    /**
     * List of custom properties that are managed by dedicated form fields and
     * therefore must not appear inside the "Autres paramètres" textarea.
     *
     * @var array<string, true>
     */
    #[Ignore]
    protected array $registeredCustomPropertyFields = [];

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
     * Return custom properties without the ones wich have a get method.
     */
    public function getStandAloneCustomProperties(): string
    {
        if ([] === $this->getCustomProperties()) {
            return '';
        }

        $standStandAloneCustomProperties = array_filter(
            $this->getCustomProperties(),
            [$this, 'isStandAloneCustomProperty'],
            \ARRAY_FILTER_USE_KEY
        );
        if ([] === $standStandAloneCustomProperties) {
            return '';
        }

        return Yaml::dump($standStandAloneCustomProperties);
    }

    public function setStandAloneCustomProperties(?string $standStandAloneCustomProperties, bool $merge = false): self
    {
        $this->standAloneCustomProperties = (string) $standStandAloneCustomProperties;

        if ($merge) {
            $this->mergeStandAloneCustomProperties();
        }

        return $this;
    }

    public function registerCustomPropertyField(string $name): self
    {
        $this->registeredCustomPropertyFields[$this->normalizeCustomPropertyIdentifier($name)] = true;

        return $this;
    }

    protected function mergeStandAloneCustomProperties(): void
    {
        $standAloneProperties = '' !== $this->standAloneCustomProperties ? Yaml::parse($this->standAloneCustomProperties)
            : [];
        if (! \is_array($standAloneProperties)) {
            throw new Exception('standAloneProperties are not a valid yaml array');
        }

        $this->standAloneCustomProperties = '';

        // remove the standAlone which were removed
        $existingPropertyNames = array_keys($this->getCustomProperties());
        foreach ($existingPropertyNames as $existingPropertyName) {
            if (! $this->isStandAloneCustomProperty((string) $existingPropertyName)) {
                continue;
            }

            if (isset($standAloneProperties[(string) $existingPropertyName])) {
                continue;
            }

            $this->removeCustomProperty((string) $existingPropertyName);
        }

        // nothing to add
        if ([] === $standAloneProperties) {
            return;
        }

        foreach ($standAloneProperties as $name => $value) {
            if (! $this->isStandAloneCustomProperty((string) $name)) {
                throw new CustomPropertiesException((string) $name);
            }

            $this->setCustomProperty((string) $name, $value);
        }
    }

    #[Assert\Callback]
    public function validateStandAloneCustomProperties(ExecutionContextInterface $executionContext): void
    {
        try {
            $this->mergeStandAloneCustomProperties();
        } catch (ParseException) {
            $executionContext->buildViolation('page.customProperties.malformed') // '$exception->getMessage())
                    ->atPath($this->buildValidationAtPath)
                    ->addViolation();
        } catch (CustomPropertiesException) {
            $executionContext->buildViolation('page.customProperties.notStandAlone') // '$exception->getMessage())
                    ->atPath($this->buildValidationAtPath)
                    ->addViolation();
        }

        // $this->validateCustomProperties($context); // too much
    }

    public function isStandAloneCustomProperty(string $name): bool
    {
        if ($this->isRegisteredCustomPropertyField($name)) {
            return false;
        }

        return ! method_exists($this, 'set'.ucfirst($name)) && ! method_exists($this, 'set'.$name);
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

    /**
     * @return array<string>
     */
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
     * Magic getter for customProperties.
     * TODO/IDEA magic setter for customProperties.
     *
     * @param mixed[] $arguments
     *
     * @return mixed|void
     */
    public function __call(string $method, array $arguments = [])
    {
        if ('_actions' === $method) {
            return null; // avoid error with sonata
        }

        if (1 === preg_match('/^get/', $method)) {
            $property = lcfirst(preg_replace('/^get/', '', $method) ?? throw new Exception());
            if (! property_exists(static::class, $property)) {
                return $this->getCustomProperty($property) ?? null;
            }

            // @phpstan-ignore-next-line
            return $this->$property;
        }

        if (! \array_key_exists($method, get_object_vars($this))) {
            return $this->getCustomProperty(lcfirst($method)) ?? null;
        }

        if (! \is_callable($getter = [$this, 'get'.ucfirst($method)])) {
            return $this->getCustomProperty(lcfirst($method)) ?? null;
        }

        return \call_user_func_array($getter, $arguments);
    }

    private function isRegisteredCustomPropertyField(string $name): bool
    {
        return isset($this->registeredCustomPropertyFields[$this->normalizeCustomPropertyIdentifier($name)]);
    }

    private function normalizeCustomPropertyIdentifier(string $name): string
    {
        return strtolower($name);
    }
}
