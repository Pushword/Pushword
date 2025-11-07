<?php

declare(strict_types=1);

use Pushword\StaticGenerator\Generator\CNAMEGenerator;
use Pushword\StaticGenerator\Generator\CopierGenerator;
use Pushword\StaticGenerator\Generator\ErrorPageGenerator;
use Pushword\StaticGenerator\Generator\MediaGenerator;
use Pushword\StaticGenerator\Generator\PagesGenerator;
use Pushword\StaticGenerator\Generator\RobotsGenerator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('pushword', [
        'apps' => [
            [
                'hosts' => [
                    'localhost.dev',
                    'localhost',
                ],
                'base_url' => 'https://localhost.dev',
                'randomTest' => 123,
                'generated_og_image' => true,
                'admin_block_editor' => false,
                'locales' => 'fr|en',
                'page_update_notification_from' => 'test@example.tld',
                'page_update_notification_to' => 'test@example.tld',
            ],
            [
                'hosts' => [
                    'pushword.piedweb.com',
                ],
                'base_url' => 'https://pushword.piedweb.com',
                'flat_content_dir' => '%kernel.project_dir%/../docs/content',
                'static_dir' => '%kernel.project_dir%/../../docs',
                'name' => 'Pushword',
                'favicon_path' => '/assets/',
                'static_generators' => [
                    PagesGenerator::class,
                    MediaGenerator::class,
                    RobotsGenerator::class,
                    ErrorPageGenerator::class,
                    CopierGenerator::class,
                    CNAMEGenerator::class,
                ],
                'assets' => [
                    'javascripts' => [
                        'assets/app.js',
                    ],
                    'stylesheets' => [
                        'assets/tw.css',
                    ],
                ],
                'color_bg' => '#fff',
                'static_copy' => [
                    'assets',
                ],
            ],
            [
                'hosts' => [
                    'admin-block-editor.test',
                ],
                'base_url' => 'https://admin-block-editor.test',
            ],
        ],
    ]);
};
