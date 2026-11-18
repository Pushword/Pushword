<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('framework', [
        'secret' => '%env(APP_SECRET)%',
        'session' => [
            'cookie_lifetime' => 0,
            'handler_id' => 'file://%kernel.project_dir%/var/sessions',
        ],
        'php_errors' => [
            'log' => true,
        ],
        'http_method_override' => false,
        'rate_limiter' => [
            'anonymous_content' => [
                'policy' => 'sliding_window',
                'limit' => 20,
                'interval' => '1 hour',
            ],
            'login_email' => [
                'policy' => 'sliding_window',
                'limit' => 20,
                'interval' => '15 minutes',
            ],
            'login_address' => [
                'policy' => 'sliding_window',
                'limit' => 5,
                'interval' => '15 minutes',
            ],
            // Login form throttling (LoginRateLimiter): Symfony's defaults for
            // max_attempts 5, counted per account and client network, and five times
            // that per client network.
            'login_throttling_account' => [
                'policy' => 'fixed_window',
                'limit' => 5,
                'interval' => '15 minutes',
            ],
            'login_throttling_network' => [
                'policy' => 'fixed_window',
                'limit' => 25,
                'interval' => '15 minutes',
            ],
        ],
    ]);
};
