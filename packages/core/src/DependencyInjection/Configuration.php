<?php

namespace Pushword\Core\DependencyInjection;

use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\User;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    /**
     * @var string
     */
    public const string DEFAULT_TEMPLATE = '@Pushword';

    /**
     * @var string[]|class-string<locale>[]
     */
    public const array DEFAULT_APP_FALLBACK = [
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
        'svg_dir',
    ];

    /**
     * @var bool
     */
    public const bool DEFAULT_ENTITY_CAN_OVERRIDE_FILTERS = true;

    /**
     * @var array<string, string>
     */
    public const array DEFAULT_FILTERS = [
        // date,email,phoneNumber ➜ managed by markdown extension in main_content
        'main_content' => 'showMore,markdown,htmlLinkMultisite,htmlObfuscateLink,mainContentSplitter,extended',
        'name' => 'twig,date,name,extended',
        'title' => 'elseH1,twig,date,extended',
        // fallback for all other properties like title, description, ...
        'string' => 'twig,date,extended',
    ];

    /**
     * @var array<string, array<string>>
     */
    public const array DEFAULT_ASSETS = [
        'vite_stylesheets' => [],
        'vite_javascripts' => [],
        'stylesheets' => ['bundles/pushwordcore/style.css'],
        'javascripts' => ['bundles/pushwordcore/app.js'],
        'favicon' => ['bundles/pushwordcore/app.js'],
    ];

    /**
     * @var mixed[]
     */
    public const array DEFAULT_CUSTOM_PROPERTIES = [];

    /**
     * @var string
     */
    public const string DEFAULT_PUBLIC_MEDIA_DIR = 'media';

    /**
     * @var array<string, array<string, mixed>>
     */
    public const array IMAGE_FILTERS_SET = [
        'default' => ['quality' => 90, 'filters' => ['scaleDown' => [1980, 1280]]],
        'height_300' => [
            'quality' => 82,
            'filters' => [
                'scaleDown' => [
                    null,
                    300,
                ],
            ],
        ],
        'thumb' => [
            'quality' => 80,
            'filters' => [
                'coverDown' => [
                    330,
                    330,
                ],
            ],
        ],
        'xs' => [
            'quality' => 85,
            'filters' => [
                'scaleDown' => [
                    576,
                ],
            ],
        ],
        'sm' => [
            'quality' => 85,
            'filters' => [
                'scaleDown' => [
                    768,
                ],
            ],
        ],
        'md' => [
            'quality' => 85,
            'filters' => [
                'scaleDown' => [
                    992,
                ],
            ],
        ],
        'lg' => [
            'quality' => 85,
            'filters' => [
                'scaleDown' => [
                    1200,
                ],
            ],
        ],
        'xl' => [
            'quality' => 85,
            'filters' => [
                'scaleDown' => [
                    1600,
                ],
            ],
        ],
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('pushword');
        $treeBuilder->getRootNode()->children()
            ->variableNode('app_fallback_properties')->defaultValue(self::DEFAULT_APP_FALLBACK)->cannotBeEmpty()->end()
            ->scalarNode('public_dir')->defaultValue('%kernel.project_dir%/public')->cannotBeEmpty()->end()
            ->scalarNode('media_dir')
                ->defaultValue('%kernel.project_dir%/media')->cannotBeEmpty()
                ->info('Dir where files will be uploaded when using admin.')
                ->end()
            ->scalarNode('public_media_dir')
                ->defaultValue(self::DEFAULT_PUBLIC_MEDIA_DIR)->cannotBeEmpty()
                ->info('Used to generate browser path. Must be accessible from public_dir.')
                ->end()
            ->scalarNode('database_url')->defaultValue('sqlite:///%kernel.project_dir%/var/app.db')->cannotBeEmpty()->end()
            ->scalarNode('entity_page')->defaultValue(Page::class)->cannotBeEmpty()->end()
            ->scalarNode('entity_media')->defaultValue(Media::class)->cannotBeEmpty()->end()
            ->scalarNode('entity_user')->defaultValue(User::class)->cannotBeEmpty()->end()

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
            ->scalarNode('base_live_url')
              ->defaultValue('https://%pw.host%')
              ->info('Could be `https://my-multi-host.tld/%pw.host%` - used in twig liveBase()')
            ->end()
            ->variableNode('assets')->defaultValue(self::DEFAULT_ASSETS)->end()
            ->variableNode('filters')->defaultValue(self::DEFAULT_FILTERS)->end()
            ->booleanNode('entity_can_override_filters')->defaultValue(self::DEFAULT_ENTITY_CAN_OVERRIDE_FILTERS)->end()
            ->scalarNode('image_filter_sets')->defaultValue(self::IMAGE_FILTERS_SET)->cannotBeEmpty()->end()
            ->scalarNode('template')->defaultValue(self::DEFAULT_TEMPLATE)->cannotBeEmpty()->end()
            ->scalarNode('template_dir')->defaultValue('%kernel.project_dir%/templates')->cannotBeEmpty()->end()
            // The following is a garbage, useful for quick new extension not well designed (no check for conf values)
            ->variableNode('custom_properties')->defaultValue(self::DEFAULT_CUSTOM_PROPERTIES)->end()
            ->variableNode('apps')->defaultValue([[]])->end()

            ->booleanNode('tailwind_generator')->defaultTrue()->end()
            ->scalarNode('path_to_bin')->defaultValue('')->end()

            ->variableNode('svg_dir')->defaultValue([
                '%kernel.project_dir%/templates/icons',
                '%vendor_dir%/fortawesome/font-awesome/free/svgs/solid',
                '%vendor_dir%/fortawesome/font-awesome/svgs/solid',
                '%vendor_dir%/fortawesome/font-awesome/free/svgs/regular',
                '%vendor_dir%/fortawesome/font-awesome/svgs/regular',
                '%vendor_dir%/fortawesome/font-awesome/free/svgs/brands',
                '%vendor_dir%/fortawesome/font-awesome/svgs/brands',
                '%kernel.project_dir%/public/bundles/pushwordcore',
            ])->cannotBeEmpty()->end()

        ->end();

        return $treeBuilder;
    }
}
