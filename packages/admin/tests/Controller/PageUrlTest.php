<?php

declare(strict_types=1);

namespace Pushword\Admin\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Site\RequestContext;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Where the admin opens a page (PageViewUrlGenerator), through every link to it.
 * Only the default app's hosts serve another app under /{host}/; any other admin
 * host must send another app's page to that app's own live URL.
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
        // The list's tie-break order may pick the homepage, whose prefixed path has no trailing slash.
        self::assertMatchesRegularExpression('#^/admin-block-editor\.test(/|$)#', $hrefs['admin-block-editor.test']);
    }

    public function testTheGatewayAdminOpensAnotherAppsHomepageOnItsBareHostPrefix(): void
    {
        // A homepage's real slug is empty, so its prefixed path has no trailing slash.
        $client = $this->loginUser();
        $page = $this->pageRepository()->findOneBy(['slug' => 'homepage', 'host' => 'admin-block-editor.test']);
        self::assertNotNull($page, 'Fixture page "homepage" must exist on admin-block-editor.test');

        $client->request(Request::METHOD_GET, $this->generateAdminUrl('admin_page_show', ['id' => $page->id]));
        self::assertResponseRedirects('/admin-block-editor.test');

        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSame($page->id, self::getContainer()->get(RequestContext::class)->currentPage?->id);
    }

    public function testAnotherAppsAdminOpensForeignPagesOnTheirLiveUrl(): void
    {
        $hrefs = $this->openOneViewLinkPerHost($this->loginUser($this->adminOn('admin-block-editor.test')));

        self::assertStringStartsWith('https://localhost.dev/', $hrefs['localhost.dev']);
        self::assertStringStartsWith('/', $hrefs['admin-block-editor.test']);
        self::assertStringNotContainsString('admin-block-editor.test', $hrefs['admin-block-editor.test']);
    }

    public function testTheTitleCellLinksThePageWhereViewDoes(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        // An admin request on a non-default app, as RequestContextListener leaves it.
        $container->get(RequestContext::class)->setRequestContext('admin-block-editor.test')->switchSite('admin-block-editor.test');
        // The cell signs its htmx posts with a CSRF token, which lives in the session.
        $request = Request::create('https://admin-block-editor.test/admin');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $container->get('request_stack')->push($request);

        // A host-less page belongs to the default app, not to the admin's current site.
        $page = new Page();
        new ReflectionProperty(Page::class, 'id')->setValue($page, 42);
        $page->slug = 'some-page';

        $html = $container->get('twig')->render('@pwAdmin/page/pageListTitleField.html.twig', ['entity' => ['instance' => $page]]);

        self::assertSame('https://localhost.dev/some-page', new Crawler($html)->filter('a.pw-page-url')->attr('href'));
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

    public function testSaveAndReturnOpensThePageWhileSaveAndContinueStaysInTheEditor(): void
    {
        $client = $this->loginUser();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $page = new Page();
        $page->host = 'localhost.dev';
        $page->locale = 'en';
        $page->slug = 'save-and-return-fixture';
        $page->h1 = 'Save and return fixture';
        $page->mainContent = 'Content';

        $entityManager->persist($page);
        $entityManager->flush();

        $pageId = (int) $page->id;

        try {
            $editUrl = $this->generateAdminUrl('admin_page_edit', ['id' => $pageId]);

            $crawler = $client->request(Request::METHOD_GET, $editUrl);
            $client->submit($crawler->filter('button[value="saveAndContinue"]')->form());
            self::assertResponseRedirects($editUrl);

            $crawler = $client->request(Request::METHOD_GET, $editUrl);
            $client->submit($crawler->filter('button[value="saveAndReturn"]')->form());
            self::assertResponseRedirects('/save-and-return-fixture');
        } finally {
            $entityManager = self::getContainer()->get(EntityManagerInterface::class);
            $saved = $entityManager->find(Page::class, $pageId);
            if (null !== $saved) {
                $entityManager->remove($saved);
                $entityManager->flush();
            }
        }
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
     * Every row's title-cell link must match its View link.
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
            $href = (string) $row->filter('.dropdown-menu .action-viewPage')->attr('href');
            self::assertSame($href, $row->filter('a.pw-page-url')->attr('href'), 'The title cell links where View does');
            $hrefById[(int) $row->attr('data-id')] = $href;
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
