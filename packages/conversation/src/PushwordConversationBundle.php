<?php

namespace Pushword\Conversation;

use Override;
use Pushword\Conversation\DependencyInjection\PushwordConversationExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class PushwordConversationBundle extends Bundle
{
    #[Override]
    public function getContainerExtension(): ?ExtensionInterface
    {
        if (null === $this->extension) {
            $this->extension = new PushwordConversationExtension();
        }

        return false === $this->extension ? null : $this->extension;
    }
}
