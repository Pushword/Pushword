<?php

namespace Pushword\PageUpdateNotifier;

use Pushword\PageUpdateNotifier\DependencyInjection\PageUpdateNotifierExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class PushwordPageUpdateNotifierBundle extends Bundle
{
    public function getContainerExtension()
    {
        if (null === $this->extension) {
            $this->extension = new PageUpdateNotifierExtension();
        }

        return $this->extension;
    }
}
