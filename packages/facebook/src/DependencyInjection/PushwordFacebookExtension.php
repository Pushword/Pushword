<?php

namespace Pushword\Facebook\DependencyInjection;

use Pushword\Core\DependencyInjection\ExtensionTrait;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

final class PushwordFacebookExtension extends ConfigurableExtension implements PrependExtensionInterface
{
    use ExtensionTrait;

    protected $configFolder = __DIR__.'/../config';
}
