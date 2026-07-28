<?php

namespace Pushword\Newsletter\DependencyInjection;

use Override;
use Pushword\Core\DependencyInjection\ExtensionTrait;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

final class PushwordNewsletterExtension extends ConfigurableExtension implements PrependExtensionInterface
{
    use ExtensionTrait;

    protected string $configFolder = __DIR__.'/../config';

    /** Matches the Configuration tree root, so parameters land under `pw.newsletter.*`. */
    #[Override]
    public function getAlias(): string
    {
        return 'newsletter';
    }
}
