<?php

/**
 * Used for test.
 */

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function getProjectDir(): string
    {
        return \dirname(__DIR__);
    }

    public function getCacheDir(): string
    {
        // Use a persistent shared cache dir so the compiled container is reused across test workers.
        // Include a hash of the config files so the cache is invalidated when service definitions change.
        if ('test' === $this->environment) {
            return sys_get_temp_dir().'/com.github.pushword.pushword/container-cache/'.$this->environment.'/'.$this->computeConfigHash();
        }

        return $this->getTestBaseDir().'/cache';
    }

    private function computeConfigHash(): string
    {
        $monoRepoBase = \dirname($this->getProjectDir());

        $files = [];
        // Config files
        $configDir = $this->getProjectDir().'/config';
        foreach (['', '/packages', '/packages/test'] as $subDir) {
            $dir = $configDir.$subDir;
            if (! is_dir($dir)) {
                continue;
            }
            foreach (new \FilesystemIterator($dir) as $file) {
                if ($file instanceof \SplFileInfo && $file->isFile()) {
                    $files[] = $file->getRealPath();
                }
            }
        }

        // All source PHP files across packages (service classes affect the compiled container)
        $srcDirs = glob($monoRepoBase.'/packages/*/src', \GLOB_ONLYDIR) ?: [];
        foreach ($srcDirs as $srcDir) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($srcDir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if ($file instanceof \SplFileInfo && $file->isFile() && 'php' === $file->getExtension()) {
                    $files[] = $file->getRealPath();
                }
            }
        }

        sort($files);
        $ctx = hash_init('sha256');
        foreach ($files as $path) {
            hash_update($ctx, $path."\0".hash_file('sha256', $path)."\0");
        }

        return substr(hash_final($ctx), 0, 16);
    }

    public function getLogDir(): string
    {
        return $this->getTestBaseDir().'/log';
    }

    private function getTestBaseDir(): string
    {
        $runId = \is_string($_ENV['TEST_RUN_ID'] ?? null) ? $_ENV['TEST_RUN_ID'] : (\is_string($_SERVER['TEST_RUN_ID'] ?? null) ? $_SERVER['TEST_RUN_ID'] : '');
        $segment = '' !== $runId ? '/'.$runId : '';

        return sys_get_temp_dir().'/com.github.pushword.pushword/tests'.$segment.'/var/'.$this->environment;
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->import('../config/{packages}/*.php');
        $container->import('../config/{packages}/*.{yaml,yml}');
        $container->import('../config/{packages}/'.$this->environment.'/*.php');
        $container->import('../config/{packages}/'.$this->environment.'/*.{yaml,yml}');

        if (is_file(\dirname(__DIR__).'/config/services.php')) {
            $container->import('../config/services.php');
            $container->import('../config/{services}_'.$this->environment.'.php');
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('../config/{routes}/'.$this->environment.'/*.php');
        $routes->import('../config/{routes}/*.php');

        if (is_file(\dirname(__DIR__).'/config/routes.yaml')) {
            $routes->import('../config/routes.yaml');
        }

        if (is_file(\dirname(__DIR__).'/config/routes.php')) {
            $routes->import('../config/routes.php');
        }
    }
}
