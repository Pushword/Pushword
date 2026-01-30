<?php

namespace Pushword\Core;

use Override;
use Pushword\Core\DependencyInjection\PushwordCoreExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class PushwordCoreBundle extends Bundle
{
    public const string SERVICE_AUTOLOAD_EXCLUDE_PATH =
        '{DependencyInjection,FormField,Resources,Entity,Migrations,Tests,config,Kernel.php,Installer/install.php,Content/ContentPipeline.php,Content/FilterContext.php}'; // \Pushword\Core\PushwordCoreBundle::SERVICE_AUTOLOAD_EXCLUDE_PATH

    #[Override]
    public function getContainerExtension(): ?ExtensionInterface
    {
        if (null === $this->extension) {
            $this->extension = new PushwordCoreExtension();
        }

        return false === $this->extension ? null : $this->extension;
    }
}
