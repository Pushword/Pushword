<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\AutowiringTrait\RequiredEntityTrait;
use Pushword\Core\AutowiringTrait\RequiredManagerPoolTrait;
use Pushword\Core\AutowiringTrait\RequiredPropertyTrait;
use Pushword\Core\Entity\PageInterface;

// TODO: Documenter
// TODO: Tester
class Extended extends AbstractFilter
{
    use RequiredEntityTrait;
    /**
     * @use RequiredManagerPoolTrait<PageInterface>
     */
    use RequiredManagerPoolTrait;
    use RequiredPropertyTrait;

    public function apply(mixed $propertyValue) // @phpstan-ignore-line
    {
        return $this->loadExtendedValue($propertyValue);
    }

    private function loadExtendedValue(mixed $propertyValue): mixed
    {
        if ('' !== $propertyValue) {
            return $propertyValue;
        }

        if (! $this->entity instanceof PageInterface) {
            return $propertyValue;
        }

        if (! $this->entity->getExtendedPage() instanceof PageInterface) {
            return $propertyValue;
        }

        $getter = 'get'.ucfirst($this->getProperty());

        return $this->entityFilterManagerPool->getManager($this->entity)->$getter(); // @phpstan-ignore-line
    }
}
