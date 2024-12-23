<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Exception;
use Pushword\Core\AutowiringTrait\RequiredManagerTrait;
use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\EntityFilter\Manager;
use Pushword\Core\Entity\Page;

class Name extends AbstractFilter
{
    public AppConfig $app;

    /**
     * @use RequiredManagerTrait<Page>
     */
    public Manager $entityFilterManager;

    public function apply(mixed $propertyValue): ?string
    {
        if (! \is_string($propertyValue)) {
            throw new Exception();
        }

        $names = explode("\n", $this->string($propertyValue));

        if ('' !== $names[0]) {
            return trim($names[0]);
        }

        return '' !== $propertyValue ? $propertyValue : $this->entityFilterManager->page->getH1();
    }
}
