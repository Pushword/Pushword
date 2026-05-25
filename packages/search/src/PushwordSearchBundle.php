<?php

namespace Pushword\Search;

use Override;
use Pushword\Search\DependencyInjection\SearchExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class PushwordSearchBundle extends Bundle
{
    #[Override]
    public function getContainerExtension(): ?ExtensionInterface
    {
        if (null === $this->extension) {
            $this->extension = new SearchExtension();
        }

        return false === $this->extension ? null : $this->extension;
    }
}
