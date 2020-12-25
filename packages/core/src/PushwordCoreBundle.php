<?php

namespace Pushword\Core;

use Pushword\Core\DependencyInjection\PushwordCoreExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class PushwordCoreBundle extends Bundle
{
    public const SERVICE_AUTOLOAD_EXCLUDE_PATH =
        '{DependencyInjection,FormField,Resources,Entity,Migrations,Tests,config,Kernel.php,Installer/install.php}'; // \Pushword\Core\PushwordCoreBundle::SERVICE_AUTOLOAD_EXCLUDE_PATH

    public function getContainerExtension(): ?ExtensionInterface
    {
        if (null === $this->extension) {
            $this->extension = new PushwordCoreExtension();
        }

        return false === $this->extension ? null : $this->extension;
    }
}
