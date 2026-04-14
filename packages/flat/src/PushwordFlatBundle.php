<?php

namespace Pushword\Flat;

use Override;
use Pushword\Flat\DependencyInjection\PushwordFlatExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class PushwordFlatBundle extends Bundle
{
    #[Override]
    public function getContainerExtension(): ?ExtensionInterface
    {
        if (null === $this->extension) {
            $this->extension = new PushwordFlatExtension();
        }

        return false === $this->extension ? null : $this->extension;
    }
}
