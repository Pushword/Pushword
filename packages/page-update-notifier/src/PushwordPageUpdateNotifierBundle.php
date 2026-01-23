<?php

namespace Pushword\PageUpdateNotifier;

use Override;
use Pushword\PageUpdateNotifier\DependencyInjection\PageUpdateNotifierExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class PushwordPageUpdateNotifierBundle extends Bundle
{
    #[Override]
    public function getContainerExtension(): ?ExtensionInterface
    {
        if (null === $this->extension) {
            $this->extension = new PageUpdateNotifierExtension();
        }

        return false === $this->extension ? null : $this->extension;
    }
}
