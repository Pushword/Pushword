<?php

namespace Pushword\Conversation;

use Pushword\Conversation\DependencyInjection\PushwordConversationExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class PushwordConversationBundle extends Bundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        if (null === $this->extension) {
            $this->extension = new PushwordConversationExtension();
        }

        return false === $this->extension ? null : $this->extension;
    }
}
