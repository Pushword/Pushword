<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use LogicException;
use Pushword\Core\Component\EntityFilter\Attribute\AsFilter;
use Pushword\Core\Component\EntityFilter\Manager;
use Pushword\Core\Entity\Page;

#[AsFilter]
class ElseH1 implements FilterInterface
{
    public function apply(mixed $propertyValue, Page $page, Manager $manager, string $property = ''): mixed
    {
        $return = '' !== $propertyValue ? $propertyValue : $page->getH1();
        if (\is_string($return)) {
            return $return;
        }

        if (null === $return) {
            return $return;
        }

        throw new LogicException();
    }
}
