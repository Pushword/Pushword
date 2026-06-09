<?php

namespace Pushword\Core\Tests\Template;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Template\TemplateResolver;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Filesystem\Filesystem;

#[Group('integration')]
final class TemplateResolverTest extends KernelTestCase
{
    public function testResolveMemoizesPerProcess(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $resolver = $container->get(TemplateResolver::class);
        $site = $container->get(SiteRegistry::class)->get();

        $first = $resolver->resolve($site, '/component/image.html.twig');
        $second = $resolver->resolve($site, '/component/image.html.twig');

        self::assertSame($first, $second);

        // The resolution is memoized in-process, so the hundreds of identical
        // getView() calls a media-rich page makes do not each hit cache.app.
        $cacheKey = 'pushword.view.'.md5($site->getMainHost().'|/component/image.html.twig|@Pushword');
        $memo = new ReflectionProperty(TemplateResolver::class, 'memo')->getValue($resolver);
        self::assertIsArray($memo);
        self::assertArrayHasKey($cacheKey, $memo);
        self::assertSame($first, $memo[$cacheKey]);
    }

    /**
     * The memo is keyed by host, so the same path must resolve independently per
     * host: a host-specific override must never leak into another host through the
     * in-memory memo (or the shared cache.app key).
     */
    public function testResolveDoesNotLeakOverrideAcrossHosts(): void
    {
        self::bootKernel();
        $registry = self::getContainer()->get(SiteRegistry::class);

        $siteWithOverride = $registry->get('localhost.dev');
        $otherSite = $registry->get('pushword.piedweb.com');
        self::assertNotSame(
            $siteWithOverride->getMainHost(),
            $otherSite->getMainHost(),
            'this test needs two distinct hosts',
        );

        $probePath = '/component/_test_resolver_probe.html.twig';
        $templateDir = $siteWithOverride->getStr('template_dir');
        $overrideFile = $templateDir.'/'.$siteWithOverride->getMainHost().$probePath;

        $filesystem = new Filesystem();
        $filesystem->dumpFile($overrideFile, '{# probe #}');

        try {
            // Fresh cache so a stale cache.app entry from a previous run (when the
            // probe file did not exist) cannot mask a host-keying regression.
            $resolver = new TemplateResolver(self::getContainer()->get('twig'), new ArrayAdapter());

            $resolvedForOverrideHost = $resolver->resolve($siteWithOverride, $probePath);
            $resolvedForOtherHost = $resolver->resolve($otherSite, $probePath);

            // The owning host gets its host-specific override...
            self::assertSame('/'.$siteWithOverride->getMainHost().$probePath, $resolvedForOverrideHost);
            // ...while the other host does NOT — the override must not leak via the memo.
            self::assertNotSame($resolvedForOverrideHost, $resolvedForOtherHost);

            // Re-resolving the owning host still returns its own override (memoized).
            self::assertSame($resolvedForOverrideHost, $resolver->resolve($siteWithOverride, $probePath));
        } finally {
            $filesystem->remove($overrideFile);
        }
    }
}
