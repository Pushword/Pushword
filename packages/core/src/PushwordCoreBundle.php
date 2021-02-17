<?php

namespace Pushword\Core;

use Pushword\Core\DependencyInjection\PushwordCoreExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class PushwordCoreBundle extends Bundle
{
    public function getContainerExtension()
    {
        if (null === $this->extension) {
            $this->extension = new PushwordCoreExtension();
        }

        return $this->extension;
    }
}
