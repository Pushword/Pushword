<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\AutowiringTrait\RequiredAppTrait;
use Pushword\Core\AutowiringTrait\RequiredManagerTrait;

class Name extends AbstractFilter
{
    use RequiredAppTrait;
    use RequiredManagerTrait;

    /** @return ?string */
    public function apply($propertyValue)
    {
        $names = explode("\n", $propertyValue);

        return $names[0] ? trim($names[0])
            : (null !== $propertyValue && '' !== $propertyValue ? $propertyValue : $this->entityFilterManager->getH1());
    }
}
