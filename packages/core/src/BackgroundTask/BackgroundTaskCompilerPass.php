<?php

namespace Pushword\Core\BackgroundTask;

use LogicException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Messenger\MessageBusInterface;

final class BackgroundTaskCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $handler = $container->getParameter('pw.background_task_handler');

        $implementation = ProcessBackgroundTaskDispatcher::class;

        if ('messenger' === $handler) {
            if (! interface_exists(MessageBusInterface::class)) {
                throw new LogicException('You have set "background_task_handler: messenger" but symfony/messenger is not installed. Run "composer require symfony/messenger" to install it.');
            }

            $implementation = MessengerBackgroundTaskDispatcher::class;
        }

        $container->setAlias(BackgroundTaskDispatcherInterface::class, $implementation)
            ->setPublic(true);
    }
}
