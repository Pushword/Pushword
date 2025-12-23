<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Exception;
use Pushword\Core\Component\EntityFilter\Attribute\AsFilter;
use Pushword\Core\Component\EntityFilter\Manager;
use Pushword\Core\Entity\Page;

#[AsFilter]
class Name implements FilterInterface
{
    public function apply(mixed $propertyValue, Page $page, Manager $manager, string $property = ''): mixed
    {
        if (! \is_string($propertyValue)) {
            throw new Exception();
        }

        $names = explode("\n", $propertyValue);

        if ('' !== $names[0]) {
            return trim($names[0]);
        }

        return '' !== $propertyValue ? $propertyValue : $page->getH1();
    }
}
