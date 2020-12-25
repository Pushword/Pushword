<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use LogicException;
use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\EntityFilter\Manager;
use Pushword\Core\Entity\Page;

class ElseH1 extends AbstractFilter
{
    public AppConfig $app;

    public Page $page;

    public Manager $entityFilterManager;

    public function apply(mixed $propertyValue): ?string
    {
        $return = '' !== $propertyValue ? $propertyValue : $this->entityFilterManager->page->getH1();
        if (\is_string($return)) {
            return $return;
        }

        if (null === $return) {
            return $return;
        }

        throw new LogicException();
    }
}
