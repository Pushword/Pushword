<?php

namespace Pushword\Api\Tests\Integration;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Pushword\Api\Controller\PageApiController;
use Pushword\Api\Workflow\WorkflowGateInterface;
use Pushword\PageWorkflow\PushwordPageWorkflowBundle;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Smoke test for the modular-install contract: a downstream site may install
 * `pushword/core` + `pushword/api` WITHOUT `pushword/page-workflow`.
 *
 * The API package wires the editorial workflow through
 * {@see WorkflowGateInterface} with `nullOnInvalid()`, so when the page-workflow
 * bundle is absent no service implements the interface and `PageApiController`
 * must still compile and receive a null gate (falling back to direct writes).
 *
 * This boots a dedicated kernel that registers every skeleton bundle EXCEPT
 * PushwordPageWorkflowBundle and asserts the container compiles with that
 * fallback in place.
 *
 * Scope note: this only covers branches that depend on SERVICE REGISTRATION
 * (`->nullOnInvalid()`). The `interface_exists()` guards in page-workflow/flat/
 * conversation/snippet are autoload-based — every class stays autoloadable in
 * the monorepo regardless of which bundle is registered, so those "api absent"
 * branches cannot be flipped here and need a real composer subset-install in CI.
 */
#[Group('integration')]
final class PartialInstallTest extends TestCase
{
    public function testApiContainerCompilesWithoutPageWorkflowBundle(): void
    {
        $kernel = $this->bootSubsetKernel();

        try {
            $container = $kernel->getContainer();

            // PageApiController is wired and the container compiled fine; no service
            // implements the gate → PageApiController falls back to direct writes.
            $expectedServices = [
                PageApiController::class => true,
                WorkflowGateInterface::class => false,
            ];
            foreach ($expectedServices as $serviceId => $shouldBeRegistered) {
                self::assertSame(
                    $shouldBeRegistered,
                    $container->has($serviceId),
                    \sprintf('Service "%s" registration mismatch in the api-without-page-workflow container.', $serviceId),
                );
            }
        } finally {
            $kernel->shutdown();
        }
    }

    private function bootSubsetKernel(): BaseKernel
    {
        $skeletonDir = \dirname(__DIR__, 3).'/skeleton';

        /** @var array<class-string<BundleInterface>, array<string, bool>> $bundles */
        $bundles = require $skeletonDir.'/config/bundles.php';
        unset($bundles[PushwordPageWorkflowBundle::class]);

        $kernel = new class('test', true, $skeletonDir, $bundles) extends BaseKernel {
            use MicroKernelTrait;

            /** @param array<class-string<BundleInterface>, array<string, bool>> $subsetBundles */
            public function __construct(
                string $environment,
                bool $debug,
                private readonly string $skeletonDir,
                private readonly array $subsetBundles,
            ) {
                parent::__construct($environment, $debug);
            }

            public function registerBundles(): iterable
            {
                foreach ($this->subsetBundles as $class => $envs) {
                    if (($envs['all'] ?? $envs[$this->environment] ?? false) === true) {
                        yield new $class();
                    }
                }
            }

            public function getProjectDir(): string
            {
                return $this->skeletonDir;
            }

            // Dedicated cache dir so we never collide with the shared full-bundle test container.
            public function getCacheDir(): string
            {
                return sys_get_temp_dir().'/com.github.pushword.pushword/partial-install-test/cache';
            }

            public function getLogDir(): string
            {
                return sys_get_temp_dir().'/com.github.pushword.pushword/partial-install-test/log';
            }

            protected function configureContainer(ContainerConfigurator $container): void
            {
                $container->import($this->skeletonDir.'/config/{packages}/*.php');
                $container->import($this->skeletonDir.'/config/{packages}/*.{yaml,yml}');
                $container->import($this->skeletonDir.'/config/{packages}/'.$this->environment.'/*.php');
                $container->import($this->skeletonDir.'/config/{packages}/'.$this->environment.'/*.{yaml,yml}');

                if (is_file($this->skeletonDir.'/config/services.php')) {
                    $container->import($this->skeletonDir.'/config/services.php');
                }
            }

            protected function configureRoutes(RoutingConfigurator $routes): void
            {
                $routes->import($this->skeletonDir.'/config/{routes}/'.$this->environment.'/*.php');
                $routes->import($this->skeletonDir.'/config/{routes}/*.php');

                if (is_file($this->skeletonDir.'/config/routes.yaml')) {
                    $routes->import($this->skeletonDir.'/config/routes.yaml');
                }

                if (is_file($this->skeletonDir.'/config/routes.php')) {
                    $routes->import($this->skeletonDir.'/config/routes.php');
                }
            }
        };

        // Force a clean compile so the subset bundle list is honoured, not a stale cache.
        $cacheDir = $kernel->getCacheDir();
        if (is_dir($cacheDir)) {
            $this->removeDir($cacheDir);
        }

        $kernel->boot();

        return $kernel;
    }

    private function removeDir(string $dir): void
    {
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            if (! $item instanceof SplFileInfo) {
                continue;
            }

            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
