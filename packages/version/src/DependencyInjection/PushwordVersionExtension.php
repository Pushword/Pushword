<?php

namespace Pushword\Version\DependencyInjection;

use Pushword\Core\DependencyInjection\ExtensionTrait;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

final class PushwordVersionExtension extends ConfigurableExtension implements PrependExtensionInterface
{
    use ExtensionTrait;

    protected string $configFolder = __DIR__.'/../config';
}
