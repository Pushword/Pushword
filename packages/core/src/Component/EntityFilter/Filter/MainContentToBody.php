<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\Component\EntityFilter\Attribute\AsFilter;
use Pushword\Core\Component\EntityFilter\Manager;
use Pushword\Core\Component\EntityFilter\ValueObject\ContentBody;
use Pushword\Core\Entity\Page;

#[AsFilter]
class MainContentToBody implements FilterInterface
{
    public function apply(mixed $propertyValue, Page $page, Manager $manager, string $property = ''): mixed
    {
        assert(is_scalar($propertyValue));

        return new ContentBody((string) $propertyValue);
    }
}
