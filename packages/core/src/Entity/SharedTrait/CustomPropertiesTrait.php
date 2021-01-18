<?php

namespace Pushword\Core\Entity\SharedTrait;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

trait CustomPropertiesTrait
{
    /**
     * YAML Format.
     *
     * @ORM\Column(type="json")
     */
    protected $customProperties = [];

    /**
     * Stand Alone for not indexed
     * Yaml format, use only for setting, else get always retrieve standAlone from $customProperties.
     *
     * @var string
     */
    protected $standAloneCustomProperties = '';

    protected $buildValidationAtPath = 'standAloneCustomProperties';

    public function getCustomProperties(): array
    {
        return $this->customProperties;
    }

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
        if (! $this->getCustomProperties()) {
            return '';
        }
        $standStandAloneCustomProperties = array_filter(
            $this->getCustomProperties(),
            [$this, 'isStandAloneCustomProperty'],
            \ARRAY_FILTER_USE_KEY
        );
        if (! $standStandAloneCustomProperties) {
            return '';
        }

        return Yaml::dump($standStandAloneCustomProperties);
    }

    public function setStandAloneCustomProperties(?string $standStandAloneCustomProperties, $merge = false): self
    {
        $this->standAloneCustomProperties = $standStandAloneCustomProperties;

        if ($merge) {
            $this->mergeStandAloneCustomProperties();
        }

        return $this;
    }

    protected function mergeStandAloneCustomProperties()
    {
        $standAloneProperties = $this->standAloneCustomProperties ? Yaml::parse($this->standAloneCustomProperties)
            : [];
        $this->standAloneCustomProperties = '';

        // remove the standAlone which were removed
        $existingPropertyNames = array_keys($this->getCustomProperties());
        foreach ($existingPropertyNames as $name) {
            if ($this->isStandAloneCustomProperty($name) && ! isset($standAloneProperties[$name])) {
                $this->removeCustomProperty($name);
            }
        }

        // nothing to add
        if (! $standAloneProperties || ! \is_array($standAloneProperties)) {
            return;
        }

        foreach ($standAloneProperties as $name => $value) {
            if (! $this->isStandAloneCustomProperty($name)) {
                throw new CustomPropertiesException($name);
            }

            $this->setCustomProperty($name, $value);
        }
    }

    /**
     * @Assert\Callback
     */
    public function validateStandAloneCustomProperties(ExecutionContextInterface $context): void
    {
        try {
            $this->mergeStandAloneCustomProperties();
        } catch (ParseException $exception) {
            $context->buildViolation('page.customProperties.malformed') //'$exception->getMessage())
                    ->atPath($this->buildValidationAtPath)
                    ->addViolation();
        } catch (CustomPropertiesException $exception) {
            $context->buildViolation('page.customProperties.notStandAlone') //'$exception->getMessage())
                    ->atPath($this->buildValidationAtPath)
                    ->addViolation();
        }

        //$this->validateCustomProperties($context); // too much
    }

    public function isStandAloneCustomProperty($name): bool
    {
        return ! method_exists($this, 'set'.ucfirst($name)) && ! method_exists($this, 'set'.$name);
    }

    public function setCustomProperty($name, $value): self
    {
        $this->customProperties[$name] = $value;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getCustomProperty(string $name)
    {
        return $this->customProperties[$name] ?? null;
    }

    public function removeCustomProperty($name): void
    {
        unset($this->customProperties[$name]);
    }

    /**
     * Magic getter for customProperties.
     * TODO/IDEA magic setter for customProperties.
     *
     * @param string $method
     * @param array  $arguments
     *
     * @return mixed
     */
    public function __call($method, $arguments)
    {
        if ('_action' == $method) {
            return; // avoid error with sonata
        }

        if (preg_match('/^get/', $method)) {
            $property = lcfirst(preg_replace('/^get/', '', $method));
            if (! property_exists(static::class, $property)) {
                return $this->getCustomProperty($property) ?? null;
                // todo remove the else next release
            }

            return $this->$property;
        } else {
            $vars = array_keys(get_object_vars($this));
            if (\in_array($method, $vars)) {
                return \call_user_func_array([$this, 'get'.ucfirst($method)], $arguments);
            }

            return $this->getCustomProperty(lcfirst($method)) ?? null;
        }
    }
}
