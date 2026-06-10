<?php

namespace Pushword\Quiz\DependencyInjection;

use Pushword\Core\DependencyInjection\ExtensionTrait;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

final class PushwordQuizExtension extends ConfigurableExtension implements PrependExtensionInterface
{
    use ExtensionTrait;

    protected string $configFolder = __DIR__.'/../config';
}
