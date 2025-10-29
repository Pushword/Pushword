<?php

namespace Pushword\Conversation\DependencyInjection;

use Override;
use Pushword\Core\DependencyInjection\ExtensionTrait;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

class PushwordConversationExtension extends ConfigurableExtension implements PrependExtensionInterface
{
    use ExtensionTrait;

    private string $configFolder = __DIR__.'/../Resources/config';

    #[Override]
    public function getAlias(): string
    {
        return 'conversation';
    }
}
