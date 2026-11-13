<?php

declare(strict_types=1);

namespace Pushword\Newsletter\DependencyInjection;

use Override;
use Pushword\Core\DependencyInjection\ExtensionTrait;
use Pushword\Newsletter\Service\EmailTemplateRegistry;
use Pushword\Newsletter\Service\SenderProfileRegistry;
use Pushword\Newsletter\Sms\SmsSettings;
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
        /** @var array<string, mixed> $sms Validated by the Configuration sms node. */
        $sms = $mergedConfig['sms'];
        unset($mergedConfig['sms']);
        $templates = $mergedConfig['email_templates'];
        $profiles = $mergedConfig['sender_profiles'];
        unset($mergedConfig['email_templates'], $mergedConfig['sender_profiles']);
        $this->loadBundleConfiguration($mergedConfig, $container);
        $container->getDefinition(SmsSettings::class)->setArguments([
            '$url' => $sms['url'], '$username' => $sms['username'], '$password' => $sms['password'],
            '$deviceId' => $sms['device_id'], '$simNumber' => $sms['sim_number'], '$enabled' => $sms['enabled'],
            '$dailyLimit' => $sms['daily_limit'], '$intervalSeconds' => $sms['interval_seconds'],
            '$startHour' => $sms['start_hour'], '$endHour' => $sms['end_hour'], '$timezone' => $sms['timezone'],
        ]);
        $container->getDefinition(EmailTemplateRegistry::class)->setArgument('$templates', $templates);
        $container->getDefinition(SenderProfileRegistry::class)->setArgument('$profiles', $profiles);
    }

    /** Matches the Configuration tree root, so parameters land under `pw.newsletter.*`. */
    #[Override]
    public function getAlias(): string
    {
        return 'newsletter';
    }
}
