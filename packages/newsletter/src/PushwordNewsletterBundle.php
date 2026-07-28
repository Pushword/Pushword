<?php

namespace Pushword\Newsletter;

use Override;
use Pushword\Newsletter\DependencyInjection\PushwordNewsletterExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class PushwordNewsletterBundle extends Bundle
{
    #[Override]
    public function getContainerExtension(): ?ExtensionInterface
    {
        if (null === $this->extension) {
            $this->extension = new PushwordNewsletterExtension();
        }

        return false === $this->extension ? null : $this->extension;
    }
}
