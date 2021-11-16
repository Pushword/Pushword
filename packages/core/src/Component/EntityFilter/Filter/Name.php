<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\AutowiringTrait\RequiredAppTrait;
use Pushword\Core\AutowiringTrait\RequiredManagerTrait;
use Pushword\Core\Entity\PageInterface;

class Name extends AbstractFilter
{
    use RequiredAppTrait;
    /**
     * @use RequiredManagerTrait<PageInterface>
     */
    use RequiredManagerTrait;

    public function apply($propertyValue): ?string
    {
        $names = explode("\n", \strval($propertyValue));

        return isset($names[0]) ? trim($names[0])
            : ('' !== $propertyValue ? $propertyValue : $this->entityFilterManager->getH1()); // @phpstan-ignore-line
    }
}
