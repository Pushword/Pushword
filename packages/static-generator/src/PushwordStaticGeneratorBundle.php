<?php

namespace Pushword\StaticGenerator;

use Pushword\StaticGenerator\DependencyInjection\StaticGeneratorExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class PushwordStaticGeneratorBundle extends Bundle
{
    public function getContainerExtension(): ?StaticGeneratorExtension
    {
        if (null === $this->extension) {
            $this->extension = new StaticGeneratorExtension();
        }

        return $this->extension;
    }
}
