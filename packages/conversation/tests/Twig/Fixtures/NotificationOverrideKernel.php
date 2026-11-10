<?php

declare(strict_types=1);

namespace Pushword\Conversation\Tests\Twig\Fixtures;

use App\Kernel;
use Override;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

final class NotificationOverrideKernel extends Kernel
{
    #[Override]
    public function getCacheDir(): string
    {
        return parent::getCacheDir().'-conversation-notification-overrides';
    }

    #[Override]
    protected function configureContainer(ContainerConfigurator $container): void
    {
        $configDir = $this->getProjectDir().'/config';
        $container->import($configDir.'/{packages}/*.{php,yaml,yml}');
        $container->import($configDir.'/{packages}/'.$this->environment.'/*.{php,yaml,yml}');
        $container->import($configDir.'/{services}.php');
        $container->import($configDir.'/{services}_'.$this->environment.'.php');
        $container->extension('twig', [
            'default_path' => __DIR__.'/notification-templates',
        ]);
    }
}
