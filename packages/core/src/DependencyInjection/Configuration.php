<?php

namespace Pushword\Core\DependencyInjection;

use Pushword\Core\Component\EntityFilter\Filter\Date;
use Pushword\Core\Component\EntityFilter\Filter\ElseH1;
use Pushword\Core\Component\EntityFilter\Filter\Extended;
use Pushword\Core\Component\EntityFilter\Filter\HtmlLinkMultisite;
use Pushword\Core\Component\EntityFilter\Filter\HtmlObfuscateLink;
use Pushword\Core\Component\EntityFilter\Filter\MainContentSplitter;
use Pushword\Core\Component\EntityFilter\Filter\Markdown;
use Pushword\Core\Component\EntityFilter\Filter\Name;
use Pushword\Core\Component\EntityFilter\Filter\ShowMore;
use Pushword\Core\Component\EntityFilter\Filter\Twig;
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
     * @var array<string, array<class-string>>
     */
    public const array DEFAULT_FILTERS = [
        // date,email,phoneNumber ➜ managed by markdown extension in main_content
        'main_content' => [
            ShowMore::class,
            Markdown::class,
            HtmlLinkMultisite::class,
            HtmlObfuscateLink::class,
            MainContentSplitter::class,
            Extended::class,
        ],
        'name' => [
            Twig::class,
            Date::class,
            Name::class,
            Extended::class,
        ],
        'title' => [
            ElseH1::class,
            Twig::class,
            Date::class,
            Extended::class,
        ],
        // fallback for all other properties like title, description, ...
        'string' => [
            Twig::class,
            Date::class,
            Extended::class,
        ],
    ];

    /**
     * @var array<string, array<string>>
     */
    public const array DEFAULT_ASSETS = [
        'vite_stylesheets' => [],
        'vite_javascripts' => [],
        'stylesheets' => ['bundles/pushwordcore/style.css'],
        'javascripts' => [
            'bundles/pushwordcore/app.js',
            'bundles/pushwordcore/alpine.js',
        ],
        'favicon' => ['bundles/pushwordcore/app.js'],
    ];

    /**
     * @var mixed[]
     */
    public const array DEFAULT_CUSTOM_PROPERTIES = [];

    /**
     * @var array<string, array<string, mixed>>
     */
    public const array IMAGE_FILTERS_SET = [
        'default' => [
            'quality' => 90,
            'filters' => ['scaleDown' => [1980, 1280]],
            'formats' => ['original', 'webp'],
        ],
        'height_300' => [
            'quality' => 82,
            'filters' => ['scaleDown' => [null, 300]],
            'formats' => ['webp'],
        ],
        'thumb' => [
            'quality' => 80,
            'filters' => ['coverDown' => [330, 330]],
            'formats' => ['webp'],
        ],
        'xs' => [
            'quality' => 85,
            'filters' => ['scaleDown' => [576]],
            'formats' => ['webp'],
        ],
        'sm' => [
            'quality' => 85,
            'filters' => ['scaleDown' => [768]],
            'formats' => ['webp'],
        ],
        'md' => [
            'quality' => 85,
            'filters' => ['scaleDown' => [992]],
            'formats' => ['webp', 'original'], // original is kept for rapid admin preview
        ],
        'lg' => [
            'quality' => 85,
            'filters' => ['scaleDown' => [1200]],
            'formats' => ['webp'],
        ],
        'xl' => [
            'quality' => 90,
            'filters' => ['scaleDown' => [1600]],
            'formats' => ['webp'],
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
                ->defaultValue('media')->cannotBeEmpty()
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
            ->enumNode('image_driver')
                ->values(['auto', 'imagick', 'gd'])
                ->defaultValue('auto')
                ->info('Image driver: auto (imagick if available, else gd), imagick, or gd')
                ->end()
            ->scalarNode('template')->defaultValue(self::DEFAULT_TEMPLATE)->cannotBeEmpty()->end()
            ->scalarNode('template_dir')->defaultValue('%kernel.project_dir%/templates')->cannotBeEmpty()->end()
            // The following is a garbage, useful for quick new extension not well designed (no check for conf values)
            ->variableNode('custom_properties')->defaultValue(self::DEFAULT_CUSTOM_PROPERTIES)->end()
            ->variableNode('apps')->defaultValue([[]])->end()

            ->booleanNode('tailwind_generator')->defaultTrue()->end()
            ->scalarNode('path_to_bin')->defaultValue('')->end()

            // PDF optimization
            ->enumNode('pdf_preset')
                ->values(['screen', 'ebook', 'printer', 'prepress'])
                ->defaultValue('ebook')
                ->info('Ghostscript preset: screen (72dpi), ebook (150dpi), printer (300dpi), prepress (300dpi+)')
                ->end()
            ->booleanNode('pdf_linearize')
                ->defaultTrue()
                ->info('Linearize PDFs for web streaming (fast first-page load)')
                ->end()

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
