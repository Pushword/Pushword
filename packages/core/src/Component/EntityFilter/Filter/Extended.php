<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\Entity\PageInterface;

// TODO: Documenter
// TODO: Tester
class Extended extends AbstractFilter
{
    use RequiredEntityTrait;
    use RequiredManagerPoolTrait;
    use RequiredPropertyTrait;

    /**
     * @return string
     */
    public function apply($propertyValue)
    {
        return $this->loadExtendedValue($propertyValue);
    }

    private function loadExtendedValue($propertyValue)
    {
        if ($propertyValue || ! $this->entity instanceof PageInterface || ! $this->entity->getExtendedPage() instanceof PageInterface) {
            return $propertyValue;
        }

        $getter = 'get'.ucfirst($this->getProperty());
        $this->entityFilterManagerPool->getManager($this->entity)->$getter();
    }
}
