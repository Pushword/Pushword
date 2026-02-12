<?php

namespace Pushword\Core;

use Override;
use Pushword\Core\BackgroundTask\BackgroundTaskCompilerPass;
use Pushword\Core\DependencyInjection\EntityClassRegistryCompilerPass;
use Pushword\Core\DependencyInjection\PushwordCoreExtension;
use Pushword\Core\Entity\EntityClassRegistry;
use Pushword\Core\Entity\User;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class PushwordCoreBundle extends Bundle
{
    public const string SERVICE_AUTOLOAD_EXCLUDE_PATH =
        '{DependencyInjection,FormField,Resources,Entity,Migrations,Tests,config,Kernel.php,Installer/install.php,Content/ContentPipeline.php,Content/FilterContext.php,BackgroundTask/MessengerBackgroundTaskDispatcher.php,BackgroundTask/RunCommandHandler.php,BackgroundTask/RunCommandMessage.php}'; // \Pushword\Core\PushwordCoreBundle::SERVICE_AUTOLOAD_EXCLUDE_PATH

    #[Override]
    public function getContainerExtension(): ?ExtensionInterface
    {
        if (null === $this->extension) {
            $this->extension = new PushwordCoreExtension();
        }

        return false === $this->extension ? null : $this->extension;
    }

    #[Override]
    public function boot(): void
    {
        parent::boot();

        /** @var class-string<User> $entityUser */
        $entityUser = $this->container?->getParameter('pw.entity_user') ?? User::class;
        EntityClassRegistry::configure($entityUser);
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new BackgroundTaskCompilerPass());
        $container->addCompilerPass(new EntityClassRegistryCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 100);
    }
}
