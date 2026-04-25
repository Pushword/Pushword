<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\Component\EntityFilter\Manager;
use Pushword\Core\Entity\Page;

interface FilterInterface
{
    public function apply(mixed $propertyValue, Page $page, Manager $manager, string $property = ''): mixed;
}
