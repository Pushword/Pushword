<?php

declare(strict_types=1);

namespace Pushword\Admin\Tests\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Service\PageViewUrlGenerator;
use Pushword\Core\Entity\Page;
use Pushword\Core\Router\PushwordRouteGenerator;
use Pushword\Core\Site\RequestContext;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Template\TemplateResolver;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

#[Group('integration')]
final class PageViewUrlGeneratorTest extends KernelTestCase
{
    /**
     * The dev-app's apps answer PHP on their base_url; here each app splits like a
     * production instance: public static pages on base_url, PHP (and drafts) on
     * base_live_url.
     */
    #[DataProvider('urlByAdminHost')]
    public function testTheUrlFollowsTheAdminHost(string $adminHost, string $pageHost, string $expected, string $slug = 'some-page'): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $apps = new SiteRegistry([
            'lab.test' => ['hosts' => ['lab.test'], 'locale' => 'en', 'base_url' => 'https://lab.test', 'base_live_url' => 'https://lab.test'],
            'site.fr' => ['hosts' => ['site.fr', 'live.site.fr'], 'locale' => 'fr', 'base_url' => 'https://site.fr', 'base_live_url' => 'https://live.site.fr'],
            // The trailing slash must not double the path's.
            'site.com' => ['hosts' => ['site.com', 'live.site.com'], 'locale' => 'en', 'base_url' => 'https://site.com', 'base_live_url' => 'https://live.site.com/'],
        ], $container->get(TemplateResolver::class), new ParameterBag());
        $context = new RequestContext($apps);
        $apps->setRequestContext($context);
        // As RequestContextListener leaves it for an admin route.
        $knownAdminHost = $apps->findHost($adminHost);
        $context->setRequestContext('' !== $knownAdminHost ? $knownAdminHost : $adminHost);

        $router = $container->get('router');
        $generator = new PageViewUrlGenerator($apps, new PushwordRouteGenerator($router, $apps), $router);

        $page = new Page();
        $page->host = $pageHost;
        $page->slug = $slug;

        self::assertSame($expected, $generator->generate($page));
    }

    /** @return iterable<string, array{0: string, 1: string, 2: string, 3?: string}> */
    public static function urlByAdminHost(): iterable
    {
        yield 'its own app' => ['live.site.fr', 'site.fr', '/some-page'];
        yield 'its own homepage' => ['live.site.fr', 'site.fr', '/', 'homepage'];
        yield 'another app, on its live URL rather than its static one' => ['live.site.com', 'site.fr', 'https://live.site.fr/some-page'];
        yield 'another app whose live URL ends with a slash' => ['live.site.fr', 'site.com', 'https://live.site.com/some-page'];
        yield "another app's homepage" => ['live.site.com', 'site.fr', 'https://live.site.fr/', 'homepage'];
        yield 'the default app, from another app' => ['live.site.com', 'lab.test', 'https://lab.test/some-page'];
        yield 'a host-less page, which the default app serves' => ['live.site.com', '', 'https://lab.test/some-page'];

        yield 'the gateway, its own app' => ['lab.test', 'lab.test', '/some-page'];
        yield 'the gateway, another app' => ['lab.test', 'site.fr', '/site.fr/some-page'];
        yield "another app's homepage, from the gateway" => ['lab.test', 'site.fr', '/site.fr', 'homepage'];
        yield 'a host-less page, on the gateway' => ['lab.test', '', '/some-page'];

        yield 'an unknown host, the default app' => ['127.0.0.1', 'lab.test', '/some-page'];
        yield 'an unknown host, another app' => ['127.0.0.1', 'site.fr', '/site.fr/some-page'];
    }
}
