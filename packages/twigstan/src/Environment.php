<?php

declare(strict_types=1);

namespace Pushword\TwigStan;

use InvalidArgumentException;
use LogicException;
use Pushword\Core\Site\RequestContext;
use Pushword\Core\Site\SiteConfig;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\TwigStan\ResolveViewNodeVisitor;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Environment as Twig;

final class Environment
{
    public static function load(KernelInterface $kernel): KernelInterface
    {
        foreach (['APP_ENV' => $kernel->getEnvironment(), 'APP_DEBUG' => $kernel->isDebug() ? '1' : '0'] as $name => $value) {
            $_ENV[$name] = $_SERVER[$name] = $value;
            putenv($name.'='.$value);
        }

        $site = self::site($kernel);
        self::service($kernel, RequestContext::class)->switchSite($site->getMainHost());
        $twig = self::twig($kernel);
        $twig->setCache(false);
        $twig->addNodeVisitor(new ResolveViewNodeVisitor($site));

        return $kernel;
    }

    public static function site(KernelInterface $kernel): SiteConfig
    {
        $kernel->boot();
        $registry = self::service($kernel, SiteRegistry::class);
        $host = getenv('PUSHWORD_TWIGSTAN_HOST') ?: $registry->getDefault()->getMainHost();

        return $registry->findByHost($host) ?? throw new InvalidArgumentException('Unknown TwigStan host: '.$host);
    }

    public static function twig(KernelInterface $kernel): Twig
    {
        $kernel->boot();

        return self::service($kernel, Twig::class, 'twig');
    }

    public static function templatePath(KernelInterface $kernel, string $path, string $fallback = '@Pushword'): string
    {
        $view = self::site($kernel)->getView($path, $fallback);

        return self::twig($kernel)->getLoader()->getSourceContext($view)->getPath();
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private static function service(KernelInterface $kernel, string $class, ?string $id = null): object
    {
        $container = $kernel->getContainer()->get('test.service_container');
        if (! $container instanceof ContainerInterface) {
            throw new LogicException('The analysis kernel must enable framework.test.');
        }

        $service = $container->get($id ?? $class);
        if (! $service instanceof $class) {
            throw new LogicException('Invalid analysis service: '.$class);
        }

        return $service;
    }
}
