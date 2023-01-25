<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\AutowiringTrait\RequiredAppTrait;
use Pushword\Core\AutowiringTrait\RequiredManagerTrait;
use Pushword\Core\Entity\PageInterface;

class ElseH1 extends AbstractFilter
{
    use RequiredAppTrait;
    /**
     * @use RequiredManagerTrait<PageInterface>
     */
    use RequiredManagerTrait;

    public function apply(mixed $propertyValue): ?string
    {
        $return = '' !== $propertyValue ? $propertyValue : $this->entityFilterManager->getEntity()->getH1();
        if (\is_string($return)) {
            return $return;
        }

        if (null === $return) {
            return $return;
        }

        throw new \LogicException();
    }
}
