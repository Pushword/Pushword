<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('knpu_oauth2_client', [
        'clients' => [
            'google' => [
                'type' => 'google',
                'client_id' => '%env(default::OAUTH_GOOGLE_CLIENT_ID)%',
                'client_secret' => '%env(default::OAUTH_GOOGLE_CLIENT_SECRET)%',
                'redirect_route' => 'pushword_oauth_google_check',
                'redirect_params' => [],
                'access_type' => 'online',
                'hosted_domain' => '%env(default::OAUTH_GOOGLE_HOSTED_DOMAIN)%',
            ],
            'microsoft' => [
                'type' => 'azure',
                'client_id' => '%env(default::OAUTH_MICROSOFT_CLIENT_ID)%',
                'client_secret' => '%env(default::OAUTH_MICROSOFT_CLIENT_SECRET)%',
                'redirect_route' => 'pushword_oauth_microsoft_check',
                'redirect_params' => [],
                'tenant' => '%env(default:oauth_microsoft_tenant_default:OAUTH_MICROSOFT_TENANT)%',
            ],
        ],
    ]);

    $containerConfigurator->parameters()
        ->set('oauth_microsoft_tenant_default', 'common');
};
