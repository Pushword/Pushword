<?php

declare(strict_types=1);

namespace Pushword\Newsletter\DependencyInjection;

use Override;
use Pushword\Core\DependencyInjection\ExtensionTrait;
use Pushword\Newsletter\Service\EmailTemplateRegistry;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

final class PushwordNewsletterExtension extends ConfigurableExtension implements PrependExtensionInterface
{
    use ExtensionTrait {
        loadInternal as private loadBundleConfiguration;
    }

    protected string $configFolder = __DIR__.'/../config';

    /** @param array<mixed> $mergedConfig */
    #[Override]
    protected function loadInternal(array $mergedConfig, ContainerBuilder $container): void
    {
        $templates = $mergedConfig['email_templates'];
        unset($mergedConfig['email_templates']);
        $this->loadBundleConfiguration($mergedConfig, $container);
        $container->getDefinition(EmailTemplateRegistry::class)->setArgument('$templates', $templates);
    }

    /** Matches the Configuration tree root, so parameters land under `pw.newsletter.*`. */
    #[Override]
    public function getAlias(): string
    {
        return 'newsletter';
    }
}
