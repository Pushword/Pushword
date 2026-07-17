<?php

namespace Pushword\Core\DependencyInjection;

use LogicException;
use Override;
use Pushword\Core\Entity\User;
use Pushword\Core\Repository\DQL\JsonExtractFunction;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

final class PushwordCoreExtension extends ConfigurableExtension implements PrependExtensionInterface
{
    use ExtensionTrait;

    private string $configFolder = __DIR__.'/../Resources/config';

    /**
     * @param array<mixed> $mergedConfig
     */
    protected function loadInternal(array $mergedConfig, ContainerBuilder $container): void
    {
        $this->setPathParameters($container);

        $configuration = $this->getConfiguration($mergedConfig, $container) ?? throw new LogicException(); // @phpstan-ignore-line

        new PushwordConfigFactory($container, $mergedConfig, $configuration)
            ->loadConfigToParams()
            ->loadApps();

        $this->loadService($container);
    }

    private function setPathParameters(ContainerBuilder $containerBuilder): void
    {
        if (file_exists($containerBuilder->getParameter('kernel.project_dir').'/vendor/pushword')) {
            // false !== strpos(__DIR__, '/vendor/')) {
            $containerBuilder->setParameter('pw.package_dir', '%kernel.project_dir%/vendor/pushword');
            $containerBuilder->setParameter('vendor_dir', '%kernel.project_dir%/vendor');

            return;
        }

        $containerBuilder->setParameter('vendor_dir', '%kernel.project_dir%/../../vendor');
        $containerBuilder->setParameter('pw.package_dir', '%kernel.project_dir%/..');
    }

    #[Override]
    public function prepend(ContainerBuilder $container): void
    {
        $this->prependPackagesConfig($container);

        $this->registerResolveTargetEntities($container);
        $this->registerDqlFunctions($container);
        $this->registerMarkdownCachePool($container);
    }

    /**
     * Dedicated filesystem pool for cached markdown→HTML fragments. Uses
     * filesystem (not cache.app, which is often APCu) so the cache persists
     * across CLI invocations — e.g. pw:page-scan — as well as web requests.
     * Fragments never expire on their own; they are keyed on a version token
     * that changes when the rendering inputs change.
     *
     * The backing directory lives NEXT TO kernel.cache_dir, not inside it:
     * cache:clear (and so every deploy) wipes the cache dir, and these
     * deterministic content-keyed fragments are exactly the cache that should
     * survive it. Renderer changes invalidate via MarkdownParser::CACHE_VERSION
     * and SplitContent::TOC_CACHE_VERSION; `cache:pool:clear
     * cache.pushword_markdown` still empties it by hand.
     */
    private function registerMarkdownCachePool(ContainerBuilder $container): void
    {
        // Mirrors the framework's abstract cache.adapter.filesystem definition,
        // directory aside. CachePoolPass seeds the namespace argument per pool.
        $container->register('pushword.cache.adapter.persistent_filesystem', FilesystemAdapter::class)
            ->setAbstract(true)
            ->setArguments([
                '',
                0,
                '%kernel.cache_dir%/../pushword-pools',
                new Reference('cache.default_marshaller', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
            ])
            ->addMethodCall('setLogger', [new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)])
            ->addTag('cache.pool', ['clearer' => 'cache.default_clearer', 'reset' => 'reset']);

        $container->prependExtensionConfig('framework', [
            'cache' => [
                'pools' => [
                    'cache.pushword_markdown' => [
                        'adapter' => 'pushword.cache.adapter.persistent_filesystem',
                        'default_lifetime' => 0,
                    ],
                ],
            ],
        ]);
    }

    private function registerDqlFunctions(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'dql' => [
                    'string_functions' => [
                        'JSON_EXTRACT' => JsonExtractFunction::class,
                    ],
                ],
            ],
        ]);
    }

    private function registerResolveTargetEntities(ContainerBuilder $container): void
    {
        $configs = $container->getExtensionConfig('pushword');
        $config = new Processor()->processConfiguration(new Configuration(), $configs);

        $resolveTargets = [];
        if (User::class !== $config['entity_user']) {
            $resolveTargets[User::class] = $config['entity_user'];
        }

        if ([] !== $resolveTargets) {
            $container->prependExtensionConfig('doctrine', [
                'orm' => [
                    'resolve_target_entities' => $resolveTargets,
                ],
            ]);
        }
    }

    #[Override]
    public function getAlias(): string
    {
        return 'pushword';
    }
}
