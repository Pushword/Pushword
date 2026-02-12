<?php

namespace Pushword\Core\DependencyInjection;

use Pushword\Core\Entity\EntityClassRegistry;
use Pushword\Core\Entity\User;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class EntityClassRegistryCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        /** @var class-string<User> $entityUser */
        $entityUser = $container->getParameter('pw.entity_user');
        EntityClassRegistry::configure($entityUser);
    }
}
