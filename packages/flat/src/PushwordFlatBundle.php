<?php

namespace Pushword\Flat;

use Pushword\Flat\DependencyInjection\PushwordFlatExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class PushwordFlatBundle extends Bundle
{
    public function getContainerExtension(): ?PushwordFlatExtension
    {
        if (null === $this->extension) {
            $this->extension = new PushwordFlatExtension();
        }

        return $this->extension;
    }
}
