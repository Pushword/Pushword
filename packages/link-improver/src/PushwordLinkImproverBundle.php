<?php

namespace Pushword\LinkImprover;

use Pushword\LinkImprover\DependencyInjection\AddLinkImproverToFilterChainPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class PushwordLinkImproverBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new AddLinkImproverToFilterChainPass());
    }
}
