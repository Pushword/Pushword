<?php

namespace Pushword\PageUpdateNotifier\DependencyInjection;

use Pushword\Core\DependencyInjection\ExtensionTrait;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

final class Bootstrap5ThemeExtension extends ConfigurableExtension implements PrependExtensionInterface
{
    use ExtensionTrait;

    protected $configFolder = __DIR__.'/../config/';
}
