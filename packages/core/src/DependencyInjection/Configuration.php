<?php

namespace Pushword\Core\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    const DEFAULT_TEMPLATE = '@Pushword';
    const DEFAULT_APP_FALLBACK = [
        'hosts',
        'locale',
        'locales',
        'name',
        'base_url',
        'template',
        'template_dir',
        'entity_can_override_filters',
        'filters',
        'assets',
        'custom_properties',
    ];
    const DEFAULT_ENTITY_CAN_OVERRIDE_FILTERS = true;

    const DEFAULT_FILTERS = [
        'main_content' => 'twig,date,email,encryptedLink,image,phoneNumber,punctuation,markdown,unprose,mainContentSplitter,extended',
        'name' => 'twig,date,name,extended',
        'title' => 'twig,date,elseH1,extended',
        'string' => 'twig,date,email,encryptedLink,phoneNumber,extended',
    ];

    const DEFAULT_ASSETS = [
        'stylesheets' => [
            '/bundles/pushwordcore/tailwind.css',
        ],
        'javascripts' => ['/bundles/pushwordcore/page.js'],
    ];

    const DEFAULT_CUSTOM_PROPERTIES = [];

    const DEFAULT_PUBLIC_MEDIA_DIR = '/media';
    const IMAGE_FILTERS_SET = [
        'default' => ['quality' => 90, 'filters' => ['downscale' => [1980, 1280]]],
        'height_300' => [
            'quality' => 82,
            'filters' => [
                'heighten' => [
                    300,
                    'constraint' => '$constraint->upsize();',
                ],
            ],
        ],
        'thumb' => [
            'quality' => 80,
            'filters' => [
                'fit' => [
                    330,
                    330,
                ],
            ],
        ],
        'xs' => [
            'quality' => 85,
            'filters' => [
                'widen' => [
                    576,
                    'constraint' => '$constraint->upsize();',
                ],
            ],
        ],
        'sm' => [
            'quality' => 85,
            'filters' => [
                'widen' => [
                    768,
                    'constraint' => '$constraint->upsize();',
                ],
            ],
        ],
        'md' => [
            'quality' => 85,
            'filters' => [
                'widen' => [
                    992,
                    'constraint' => '$constraint->upsize();',
                ],
            ],
        ],
        'lg' => [
            'quality' => 85,
            'filters' => [
                'widen' => [
                    1200,
                    'constraint' => '$constraint->upsize();',
                ],
            ],
        ],
        'xl' => [
            'quality' => 85,
            'filters' => [
                'widen' => [
                    1600,
                    'constraint' => '$constraint->upsize();',
                ],
            ],
        ],
    ];

    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('pushword');
        $treeBuilder->getRootNode()->children()
            ->scalarNode('public_dir')->defaultValue('%kernel.project_dir%/public')->cannotBeEmpty()->end()
            ->scalarNode('database_url')->defaultValue('sqlite:///%kernel.project_dir%/var/app.db')->cannotBeEmpty()->end()
            ->scalarNode('entity_page')->defaultValue('App\Entity\Page')->cannotBeEmpty()->end()
            ->scalarNode('entity_media')->defaultValue('App\Entity\Media')->cannotBeEmpty()->end()
            ->scalarNode('entity_user')->defaultValue('App\Entity\User')->cannotBeEmpty()->end()
            ->scalarNode('media_dir')
                ->defaultValue('%kernel.project_dir%/media')->cannotBeEmpty()
                ->info('Dir where files will be uploaded when using admin.')
                ->end()
            ->scalarNode('public_media_dir')
                ->defaultValue(self::DEFAULT_PUBLIC_MEDIA_DIR)->cannotBeEmpty()
                ->info('Used to generate browser path. Must be accessible from public_dir.')
                ->end()
            ->variableNode('app_fallback_properties')->defaultValue(self::DEFAULT_APP_FALLBACK)->cannotBeEmpty()->end()
            // default app value
            ->scalarNode('locale')->defaultValue('%kernel.default_locale%')->cannotBeEmpty()->end()
            ->scalarNode('locales')
                ->info('eg: fr|en')
                ->defaultValue('%kernel.default_locale%')
                ->end()
            ->scalarNode('name')->defaultValue('Pushword')->end()
            ->variableNode('host')->defaultValue('localhost')->end()
            ->variableNode('hosts')->defaultValue(['%pw.host%'])->end()
            ->scalarNode('base_url')->defaultValue('https://%pw.host%')->end()
            ->variableNode('assets')->defaultValue(self::DEFAULT_ASSETS)->end()
            ->variableNode('filters')->defaultValue(self::DEFAULT_FILTERS)->end()
            ->booleanNode('entity_can_override_filters')->defaultValue(self::DEFAULT_ENTITY_CAN_OVERRIDE_FILTERS)->end()
            ->scalarNode('image_filter_sets')->defaultValue(self::IMAGE_FILTERS_SET)->cannotBeEmpty()->end()
            ->scalarNode('template')->defaultValue(self::DEFAULT_TEMPLATE)->cannotBeEmpty()->end()
            ->scalarNode('template_dir')->defaultValue('%kernel.project_dir%/templates')->cannotBeEmpty()->end()
            // The following is a garbage, useful for quick new extension not well designed (no check for conf values)
            ->variableNode('custom_properties')->defaultValue(self::DEFAULT_CUSTOM_PROPERTIES)->end()

            ->variableNode('apps')->defaultValue([[]])->end()
        ->end();

        return $treeBuilder;
    }
}
