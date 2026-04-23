<?php

namespace Pushword\StaticGenerator\DependencyInjection;

use Deprecated;
use Pushword\StaticGenerator\Generator\CaddyfileGenerator;
use Pushword\StaticGenerator\Generator\CNAMEGenerator;
use Pushword\StaticGenerator\Generator\CopierGenerator;
use Pushword\StaticGenerator\Generator\ErrorPageGenerator;
use Pushword\StaticGenerator\Generator\GeneratorInterface;
use Pushword\StaticGenerator\Generator\HtaccessGenerator;
use Pushword\StaticGenerator\Generator\MediaGenerator;
use Pushword\StaticGenerator\Generator\PagesCompressor;
use Pushword\StaticGenerator\Generator\PagesGenerator;
use Pushword\StaticGenerator\Generator\RobotsGenerator;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    final public const array DEFAULT_APP_FALLBACK = [
        'static_generators',
        'static_symlink',
        'static_dir',
        'static_assets',
        'static_assets_clean',
        'static_copy',
        'static_html_max_age',
        'static_html_stale_while_revalidate',
        'cache',
    ];

    /**
     * Generators used in `cache: static` mode — we only want HTML + media, nothing web-server-global.
     *
     * @var array<class-string<GeneratorInterface>>
     */
    final public const array DEFAULT_GENERATOR_CACHE = [
        PagesGenerator::class,
        ErrorPageGenerator::class,
        MediaGenerator::class,
        PagesCompressor::class,
    ];

    /**
     * The default generator covers Apache/Litespeed and FrankenPHP/Caddy.
     *
     * @var array<class-string<GeneratorInterface>>
     */
    final public const array DEFAULT_GENERATOR = [
        PagesGenerator::class,
        RobotsGenerator::class,
        ErrorPageGenerator::class,
        CopierGenerator::class,
        MediaGenerator::class,
        HtaccessGenerator::class,
        CaddyfileGenerator::class,
        PagesCompressor::class,
    ];

    /**
     * @var array<class-string<GeneratorInterface>>
     */
    final public const array DEFAULT_GENERATOR_APACHE = [
        PagesGenerator::class,
        RobotsGenerator::class,
        ErrorPageGenerator::class,
        CopierGenerator::class,
        MediaGenerator::class,
        HtaccessGenerator::class,
        PagesCompressor::class,
    ];

    /**
     * @var array<class-string<GeneratorInterface>>
     */
    final public const array DEFAULT_GENERATOR_GITHUB = [
        PagesGenerator::class,
        RobotsGenerator::class,
        ErrorPageGenerator::class,
        CopierGenerator::class,
        MediaGenerator::class,
        CNAMEGenerator::class,
    ];

    /**
     * @var array<class-string<GeneratorInterface>>
     */
    final public const array DEFAULT_GENERATOR_FRANKENPHP = [
        PagesGenerator::class,
        RobotsGenerator::class,
        ErrorPageGenerator::class,
        CopierGenerator::class,
        MediaGenerator::class,
        CaddyfileGenerator::class,
        PagesCompressor::class,
    ];

    /**
     * @var string[]
     */
    final public const array DEFAULT_ASSETS = ['assets', 'bundles'];

    #[Deprecated(message: 'Use DEFAULT_ASSETS instead')]
    final public const array DEFAULT_COPY = self::DEFAULT_ASSETS;

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('static_generator');
        $treeBuilder->getRootNode()->children()
            ->variableNode('app_fallback_properties')->defaultValue(self::DEFAULT_APP_FALLBACK)->cannotBeEmpty()->end()
            ->variableNode('static_symlink')
                ->info("true/false for all, or array of ['media', 'assets'] to symlink selectively. GitHub pages forces copy.")
                ->defaultTrue()
                ->validate()
                    ->ifTrue(static function (mixed $v): bool {
                        if (\is_bool($v)) {
                            return false;
                        }

                        if (\is_array($v)) {
                            /** @var array<string> $v */
                            return [] !== array_diff($v, ['media', 'assets']);
                        }

                        return true;
                    })
                    ->thenInvalid('static_symlink must be a bool or an array containing only "media" and/or "assets".')
                ->end()
            ->end()
            ->variableNode('static_generators')
                ->defaultValue(self::DEFAULT_GENERATOR)
                ->validate()
                    ->ifInArray(['apache'])->then(static fn (): array => self::DEFAULT_GENERATOR_APACHE)
                    ->ifInArray(['github'])->then(static fn (): array => self::DEFAULT_GENERATOR_GITHUB)
                    ->ifInArray(['frankenphp'])->then(static fn (): array => self::DEFAULT_GENERATOR_FRANKENPHP)
                ->end()
            ->end()

            ->scalarNode('static_dir')
                ->defaultValue('%kernel.project_dir%/static/{main_host}')
                ->info('If null or empty, static dir will be %kernel.project_dir%/static/{main_host}/.')
            ->end()
            ->variableNode('static_assets')
                ->info('file or folder in your public dir to copy in static')
                ->defaultValue(self::DEFAULT_ASSETS)
            ->end()
            ->booleanNode('static_assets_clean')
                ->info('Remove stale files in static assets dir before copying (useful with Vite hashed filenames)')
                ->defaultFalse()
            ->end()
            ->variableNode('static_copy')
                ->info('Deprecated: use static_assets instead')
                ->defaultValue(self::DEFAULT_ASSETS)
            ->end()
            ->integerNode('static_html_max_age')
                ->info('Cache TTL for HTML pages in seconds (default: 10800 = 3 hours)')
                ->defaultValue(10800)
            ->end()
            ->integerNode('static_html_stale_while_revalidate')
                ->info('stale-while-revalidate TTL in seconds, 0 to disable (default: 3600 = 1 hour)')
                ->defaultValue(3600)
            ->end()
            ->enumNode('cache')
                ->info('Page cache mode: "none" (default) or "static" (pre-render pages to public/cache/{host}/ served by Caddy/Apache without booting PHP)')
                ->values(['none', 'static'])
                ->defaultValue('none')
            ->end()
        ->end();

        return $treeBuilder;
    }
}
