<?php

namespace Pushword\Conversation\DependencyInjection;

use Pushword\Core\DependencyInjection\ExtensionTrait;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

class PushwordConversationExtension extends ConfigurableExtension implements PrependExtensionInterface
{
    use ExtensionTrait;

    /**
     * @var string
     */
    private $configFolder = __DIR__.'/../Resources/config';

    public function getAlias(): string
    {
        return 'conversation';
    }
}
