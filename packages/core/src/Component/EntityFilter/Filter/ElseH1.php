<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

class ElseH1 extends AbstractFilter
{
    use RequiredAppTrait;
    use RequiredManagerTrait;

    /** @return ?string */
    public function apply($propertyValue)
    {
        return $propertyValue ?: $this->entityFilterManager->getH1();
    }
}
