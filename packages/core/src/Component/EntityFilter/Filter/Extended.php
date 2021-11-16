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

    public function apply($propertyValue)
    {
        return $this->loadExtendedValue($propertyValue);
    }

    /**
     * @param mixed $propertyValue
     *
     * @return mixed
     */
    private function loadExtendedValue($propertyValue)
    {
        if ('' !== $propertyValue || ! $this->entity instanceof PageInterface || ! $this->entity->getExtendedPage() instanceof PageInterface) {
            return $propertyValue;
        }

        $getter = 'get'.ucfirst($this->getProperty());

        return $this->entityFilterManagerPool->getManager($this->entity)->$getter(); // @phpstan-ignore-line
    }
}
