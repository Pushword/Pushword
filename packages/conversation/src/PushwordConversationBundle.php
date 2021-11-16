<?php

namespace Pushword\Conversation;

use Pushword\Conversation\DependencyInjection\PushwordConversationExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class PushwordConversationBundle extends Bundle
{
    public function getContainerExtension(): ?PushwordConversationExtension
    {
        if (null === $this->extension) {
            $this->extension = new PushwordConversationExtension();
        }

        return $this->extension;
    }
}
