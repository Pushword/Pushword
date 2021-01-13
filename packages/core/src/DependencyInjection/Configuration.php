<?php

namespace Pushword\Core\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    const DEFAULT_TEMPLATE = '@Pushword';
    const DEFAULT_APP_FALLBACK = [
        'hosts',
        'public_dir',
        'locale',
        'locales',
        'name',
        'base_url',
        'template',
        'template_dir',
        'custom_properties',
    ];
    const DEFAULT_CUSTOM_PROPERTIES = [
        'main_content_type' => 'Raw', // not anymore used, replaced by filters... to remove
        'can_use_twig_shortcode' => true,
        'main_content_shortcode' => 'twig,date,email,encryptedLink,image,phoneNumber,twigVideo,punctuation,markdown',
        'fields_shortcode' => 'twig,date,email,encryptedLink,phoneNumber',
        'assets' => [
            'stylesheets' => [
                '/bundles/pushwordcore/tailwind.css',
            ],
            'javascripts' => ['/bundles/pushwordcore/page.js'],
        ],
    ];
    const DEFAULT_TWIG_SHORTCODE = true;

    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('pushword');
        $treeBuilder->getRootNode()->children()
            // not explicit => public dir web dir...
            // used in PageScanner,StaticGenerator,MediaWebPathResolver to find public/index.php TODO rplce it
            // for the symfony parameters
            ->scalarNode('dir')->defaultValue('%kernel.project_dir%/public')->cannotBeEmpty()->end()
            ->scalarNode('public_dir')->defaultValue('%kernel.project_dir%/public')->cannotBeEmpty()->end()
            ->scalarNode('entity_page')->defaultValue('App\Entity\Page')->cannotBeEmpty()->end()
            ->scalarNode('entity_media')->defaultValue('App\Entity\Media')->cannotBeEmpty()->end()
            ->scalarNode('entity_user')->defaultValue('App\Entity\User')->cannotBeEmpty()->end()
            ->scalarNode('entity_pagehasmedia')->defaultValue('App\Entity\PageHasMedia')->cannotBeEmpty()->end()
            ->scalarNode('media_dir')->defaultValue('%kernel.project_dir%/media')->cannotBeEmpty()->end()
            ->variableNode('app_fallback_properties')->defaultValue(self::DEFAULT_APP_FALLBACK)->cannotBeEmpty()->end()
            // default app value
            ->scalarNode('locale')->defaultValue('%locale%')->cannotBeEmpty()->end()
            ->scalarNode('locales')
                ->info('eg: fr|en')
                ->defaultValue('%locale%')
                ->end()
            ->scalarNode('name')->defaultValue('Pushword')->end()
            ->variableNode('host')->defaultValue('localhost')->end()
            ->variableNode('hosts')->defaultValue(['%pw.host%'])->end()
            ->scalarNode('base_url')->defaultValue('https://%pw.host%')->end()
            ->scalarNode('template')->defaultValue(self::DEFAULT_TEMPLATE)->cannotBeEmpty()->end()
            ->scalarNode('template_dir')->defaultValue('%kernel.project_dir%/templates')->cannotBeEmpty()->end()
            // The following is a garbage, useful for quick new extension not well designed (no check for conf values)
            ->variableNode('custom_properties')->defaultValue(self::DEFAULT_CUSTOM_PROPERTIES)->end()

            ->variableNode('apps')->defaultValue([[]])->end()
        ->end();

        return $treeBuilder;
    }
}
