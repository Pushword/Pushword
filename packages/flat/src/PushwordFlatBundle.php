<?php

namespace Pushword\Flat;

use Override;
use Pushword\Flat\DependencyInjection\PushwordFlatExtension;
use Pushword\Flat\Sync\FlatSyncInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class PushwordFlatBundle extends Bundle
{
    #[Override]
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Other packages contribute a flat sync for their own entity by
        // implementing FlatSyncInterface; FlatFileSync drives them all.
        $container->registerForAutoconfiguration(FlatSyncInterface::class)
            ->addTag('pushword.flat.sync');
    }

    #[Override]
    public function getContainerExtension(): ?ExtensionInterface
    {
        if (null === $this->extension) {
            $this->extension = new PushwordFlatExtension();
        }

        return false === $this->extension ? null : $this->extension;
    }
}
