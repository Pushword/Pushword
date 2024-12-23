<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\Component\EntityFilter\ManagerPool;
use Pushword\Core\Entity\Page;

// TODO: Documenter
// TODO: Tester
class Extended extends AbstractFilter
{
    public ManagerPool $managerPool;

    public Page $page;

    public string $property;

    public function apply(mixed $propertyValue): mixed
    {
        return $this->loadExtendedValue($propertyValue);
    }

    private function loadExtendedValue(mixed $propertyValue): mixed
    {
        if ('' !== $propertyValue) {
            return $propertyValue;
        }

        if (! $this->page->getExtendedPage() instanceof Page) {
            return $propertyValue;
        }

        $getter = 'get'.ucfirst($this->property);

        return $this->managerPool->getManager($this->page)->$getter(); // @phpstan-ignore-line
    }
}
