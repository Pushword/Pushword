<?php

namespace Pushword\StaticGenerator;

use Override;
use Pushword\StaticGenerator\DependencyInjection\StaticGeneratorExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class PushwordStaticGeneratorBundle extends Bundle
{
    #[Override]
    public function getContainerExtension(): ?ExtensionInterface
    {
        if (null === $this->extension) {
            $this->extension = new StaticGeneratorExtension();
        }

        return false === $this->extension ? null : $this->extension;
    }
}
