<?php

declare(strict_types=1);

namespace Pushword\Admin\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\AdminFormFieldManager;
use Pushword\Admin\Controller\PageCrudController;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Site\RequestContext;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Template\TemplateResolver;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Where the admin opens a page (PageCrudController::getPageUrl). Only the default
 * app's hosts serve another app under /{host}/; any other admin host must send
 * another app's page to that app's own live URL.
 */
#[Group('integration')]
final class PageUrlTest extends AbstractAdminTestClass
{
    public function testTheGatewayAdminOpensEveryAppUnderItsHostPrefix(): void
    {
        // The test client's host, localhost, is the default app's.
        $hrefs = $this->openOneViewLinkPerHost($this->loginUser());

        self::assertStringStartsWith('/', $hrefs['localhost.dev']);
        self::assertStringNotContainsString('localhost.dev', $hrefs['localhost.dev']);
        self::assertStringStartsWith('/admin-block-editor.test/', $hrefs['admin-block-editor.test']);
    }

    public function testAnotherAppsAdminOpensForeignPagesOnTheirLiveUrl(): void
    {
        $hrefs = $this->openOneViewLinkPerHost($this->loginUser($this->adminOn('admin-block-editor.test')));

        self::assertStringStartsWith('https://localhost.dev/', $hrefs['localhost.dev']);
        self::assertStringStartsWith('/', $hrefs['admin-block-editor.test']);
        self::assertStringNotContainsString('admin-block-editor.test', $hrefs['admin-block-editor.test']);
    }

    public function testTheEditFormLinksAnotherAppsPageToItsLiveUrl(): void
    {
        // The production case: an admin on one non-default app (live.piedweb.com)
        // linked a page of another (piedweb.fr) under a /{host}/ path its own host ignores.
        $client = $this->loginUser($this->adminOn('pushword.piedweb.com'));
        $page = $this->pageRepository()->findOneBy(['slug' => 'kitchen-sink', 'host' => 'admin-block-editor.test']);
        self::assertNotNull($page, 'Fixture page "kitchen-sink" must exist on admin-block-editor.test');

        $crawler = $client->request(Request::METHOD_GET, $this->generateAdminUrl('admin_page_edit', ['id' => $page->id]));
        self::assertResponseIsSuccessful();

        $link = $crawler->filter('#disabledLinkSlug a');
        self::assertCount(1, $link);
        self::assertSame('https://admin-block-editor.test/kitchen-sink', $link->attr('href'));
    }

    public function testTheDetailActionRedirectsToThePageUrl(): void
    {
        $client = $this->loginUser($this->adminOn('pushword.piedweb.com'));
        $page = $this->pageRepository()->findOneBy(['slug' => 'kitchen-sink', 'host' => 'admin-block-editor.test']);
        self::assertNotNull($page, 'Fixture page "kitchen-sink" must exist on admin-block-editor.test');

        $client->request(Request::METHOD_GET, $this->generateAdminUrl('admin_page_show', ['id' => $page->id]));

        self::assertResponseRedirects('https://admin-block-editor.test/kitchen-sink');
    }

    /**
     * The dev-app's apps answer PHP on their base_url; here each app splits like a
     * production instance: public static pages on base_url, PHP (and drafts) on
     * base_live_url.
     */
    #[DataProvider('pageUrlByAdminHost')]
    public function testThePageUrlFollowsTheAdminHost(string $adminHost, string $pageHost, string $expected, string $slug = 'some-page'): void
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

        $controller = $container->get(PageCrudController::class);
        $controller->injectBaseServices(
            $apps,
            $container->get(EntityManagerInterface::class),
            $container->get(RequestStack::class),
            $container->get(TranslatorInterface::class),
            $container->get(AdminFormFieldManager::class),
        );

        $page = new Page();
        $page->host = $pageHost;
        $page->slug = $slug;

        self::assertSame($expected, $controller->getPageUrl($page));
    }

    /** @return iterable<string, array{0: string, 1: string, 2: string, 3?: string}> */
    public static function pageUrlByAdminHost(): iterable
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

    private function adminOn(string $host): KernelBrowser
    {
        return self::createClient(server: ['HTTP_HOST' => $host]);
    }

    private function pageRepository(): PageRepository
    {
        return self::getContainer()->get(PageRepository::class);
    }

    /**
     * Follow the list's View link of one page per host and check it renders that page.
     *
     * @return array<string, string> href by page host
     */
    private function openOneViewLinkPerHost(KernelBrowser $client): array
    {
        $crawler = $client->request(Request::METHOD_GET, $this->generateAdminUrl('admin_page_list'));
        self::assertResponseIsSuccessful();

        /** @var array<int, string> $hrefById */
        $hrefById = [];
        $crawler->filter('.datagrid tbody tr[data-id]')->each(static function (Crawler $row) use (&$hrefById): void {
            $hrefById[(int) $row->attr('data-id')] = (string) $row->filter('.dropdown-menu .action-viewPage')->attr('href');
        });

        $hrefs = [];
        foreach ($hrefById as $id => $href) {
            $page = $this->pageRepository()->find($id);
            self::assertNotNull($page);
            if (isset($hrefs[$page->host]) || $page->hasRedirection()) {
                continue;
            }

            $client->request(Request::METHOD_GET, $href);
            self::assertResponseIsSuccessful($href);
            self::assertSame($id, self::getContainer()->get(RequestContext::class)->currentPage?->id, $href.' renders another page');

            $hrefs[$page->host] = $href;
        }

        self::assertArrayHasKey('localhost.dev', $hrefs, 'The list must show a localhost.dev page');
        self::assertArrayHasKey('admin-block-editor.test', $hrefs, 'The list must show an admin-block-editor.test page');

        return $hrefs;
    }
}
