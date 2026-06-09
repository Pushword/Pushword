<?php

namespace Pushword\Core\Tests\Template;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Template\TemplateResolver;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

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
}
