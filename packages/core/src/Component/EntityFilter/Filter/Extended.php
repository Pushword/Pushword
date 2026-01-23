<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\Component\EntityFilter\Attribute\AsFilter;
use Pushword\Core\Component\EntityFilter\Manager;
use Pushword\Core\Entity\Page;

#[AsFilter]
class Extended implements FilterInterface
{
    /** @var array<string, true> */
    private array $resolutionStack = [];

    public function apply(mixed $propertyValue, Page $page, Manager $manager, string $property = ''): mixed
    {
        if ('' !== $propertyValue) {
            return $propertyValue;
        }

        $extendedPage = $page->extendedPage;
        if (! $extendedPage instanceof Page) {
            return $propertyValue;
        }

        $stackKey = ($page->id ?? spl_object_id($page)).':'.$property;

        if (isset($this->resolutionStack[$stackKey])) {
            return $propertyValue;
        }

        $this->resolutionStack[$stackKey] = true;

        try {
            $getter = 'get'.ucfirst($property);

            return $manager->getManagerPool()->getManager($extendedPage)->$getter(); // @phpstan-ignore-line
        } finally {
            unset($this->resolutionStack[$stackKey]);
        }
    }
}
