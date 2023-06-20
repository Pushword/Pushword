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

    public function apply(mixed $propertyValue): ?string
    {
        $names = explode("\n", $this->string($propertyValue));

        return isset($names[0]) && '' !== $names[0] ? trim($names[0])
            : ('' !== $propertyValue ? $propertyValue : $this->entityFilterManager->getH1()); // @phpstan-ignore-line
    }
}
