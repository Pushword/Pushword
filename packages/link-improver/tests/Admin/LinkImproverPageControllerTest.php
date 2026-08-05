<?php

namespace Pushword\LinkImprover\Tests\Admin;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Entity\Page;
use Pushword\Core\Site\SiteRegistry;
use Pushword\LinkImprover\InternalLinkSources;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
final class LinkImproverPageControllerTest extends AbstractAdminTestClass
{
    private const string HOST = 'localhost.dev';

    protected function tearDown(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $stale = $entityManager->getRepository(Page::class)->createQueryBuilder('p')
            ->where("p.slug LIKE 'linkimppanel-%'")->getQuery()->getResult();
        foreach ($stale as $page) {
            $entityManager->remove($page);
        }

        $entityManager->flush();

        parent::tearDown();
    }

    /**
     * The panel renders through the live container, so the kernel must survive
     * from the moment the app opts in to the moment the page is requested.
     */
    private function loginAndHold(): KernelBrowser
    {
        $client = $this->loginUser();
        $client->disableReboot();

        return $client;
    }

    private function enable(float $maxLinks = 5.0): void
    {
        $site = self::getContainer()->get(SiteRegistry::class)->get(self::HOST);
        $site->setCustomProperty('link_improver', true);
        $site->setCustomProperty('link_improver_max_links', $maxLinks);
        self::getContainer()->get(InternalLinkSources::class)->reset();
    }

    /** Opting in without naming a cap, so the app's configured default applies. */
    private function enableWithDefaultCap(): void
    {
        self::getContainer()->get(SiteRegistry::class)->get(self::HOST)
            ->setCustomProperty('link_improver', true);
        self::getContainer()->get(InternalLinkSources::class)->reset();
    }

    private function disable(): void
    {
        self::getContainer()->get(SiteRegistry::class)->get(self::HOST)
            ->setCustomProperty('link_improver', false);
    }

    /** @param list<string> $urls */
    private function ignoreUrls(array $urls): void
    {
        self::getContainer()->get(SiteRegistry::class)->get(self::HOST)
            ->setCustomProperty('link_improver_ignored_urls', $urls);
    }

    private function createPage(string $slug, string $name, string $mainContent): Page
    {
        $page = new Page();
        $page->host = self::HOST;
        $page->slug = $slug;
        $page->name = $name;
        $page->h1 = 'Panel '.$slug;
        $page->locale = 'en';
        $page->publishedAt = new DateTime('-1 hour');
        $page->mainContent = $mainContent;

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($page);
        $entityManager->flush();

        return $page;
    }

    private function url(int $id): string
    {
        return self::getContainer()->get('router')->generate('admin_link_improver_page', ['id' => $id]);
    }

    private function open(KernelBrowser $client, Page $page): string
    {
        $client->request(Request::METHOD_GET, $this->url((int) $page->id));

        $content = (string) $client->getResponse()->getContent();
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), $content);

        return $content;
    }

    public function testItListsTheLinksTheImproverInserted(): void
    {
        $client = $this->loginAndHold();
        $this->enable();

        $this->createPage('linkimppanel-kiwano', "Kiwano Melano\nhorned melon", 'The target page.');
        $context = $this->createPage('linkimppanel-context', 'Context', 'A paragraph mentioning Kiwano Melano in passing.');

        $content = $this->open($client, $context);

        self::assertStringContainsString('Kiwano Melano', $content);
        self::assertStringContainsString('/linkimppanel-kiwano', $content);

        // page_actions is silently dropped when misnamed — assert it rendered.
        $editUrl = self::getContainer()->get('router')->generate('admin_page_edit', ['entityId' => $context->id]);
        self::assertStringContainsString($editUrl, $content, 'the panel links back to the page edit screen');
    }

    public function testItReportsTheCapAndTheWordCount(): void
    {
        $client = $this->loginAndHold();
        $this->enable(maxLinks: 5.0);

        $this->createPage('linkimppanel-kiwano', 'Kiwano Melano', 'The target page.');
        $context = $this->createPage(
            'linkimppanel-cap',
            'Cap',
            'An [existing link](/linkimppanel-kiwano) and some more words to count here.'
        );

        $content = $this->open($client, $context);

        // The absolute cap, and the one link the editor wrote — counted before
        // the improver added anything.
        self::assertMatchesRegularExpression('/Cap: ?5 link/', $content);
        self::assertStringContainsString('1 already written', $content);
        self::assertMatchesRegularExpression('/\d+ words/', $content);
    }

    public function testItSaysSoWhenTheAppHasNotOptedIn(): void
    {
        $client = $this->loginAndHold();
        $this->disable();

        $page = $this->createPage('linkimppanel-off', 'Off', 'A paragraph mentioning Kiwano Melano.');

        $content = $this->open($client, $page);

        self::assertStringContainsString('link_improver', $content);
        self::assertStringNotContainsString('already written', $content);
    }

    public function testItShowsTheKeywordsThatMakeThePageATarget(): void
    {
        $client = $this->loginAndHold();
        $this->enable();

        $page = $this->createPage('linkimppanel-kw', "Kiwano Melano\nhorned melon", 'The target page.');

        $content = $this->open($client, $page);

        self::assertStringContainsString('Kiwano Melano', $content);
        self::assertStringContainsString('horned melon', $content);
    }

    /** The default cap is a ratio of the word count, and must not read as a fixed count. */
    public function testTheDefaultCapIsReportedAsARatio(): void
    {
        $client = $this->loginAndHold();
        $this->enableWithDefaultCap();

        $this->createPage('linkimppanel-kiwano', 'Kiwano Melano', 'The target page.');
        $page = $this->createPage('linkimppanel-ratio', 'Ratio', 'Some words to measure a ratio against.');

        $content = $this->open($client, $page);

        self::assertStringContainsString('Cap:', $content);
        self::assertStringNotContainsString('fixed count', $content);
    }

    /**
     * A page whose host offers no keyword at all never reaches the engine, so it
     * has no numbers to show — that is a different answer from "nothing matched".
     */
    public function testItTellsTheTargetlessCaseApartFromTheEmptyOne(): void
    {
        $client = $this->loginAndHold();
        $this->enable();

        $page = $this->createPage('linkimppanel-alone', 'Alone', 'A page with no named neighbour.');

        $content = $this->open($client, $page);

        self::assertStringContainsString('offers a keyword to link to', $content);
        self::assertStringNotContainsString('Cap:', $content);
    }

    public function testItSaysWhenThePageIsExcludedFromBeingATarget(): void
    {
        $client = $this->loginAndHold();
        $this->enable();
        $this->ignoreUrls(['/linkimppanel-ignored']);

        $page = $this->createPage('linkimppanel-ignored', 'Ignored Page', 'The excluded page.');

        $content = $this->open($client, $page);

        self::assertStringContainsString('link_improver_ignored_urls', $content);
    }

    public function testAnUnknownPageIs404(): void
    {
        $client = $this->loginAndHold();

        $client->request(Request::METHOD_GET, $this->url(99999999));

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }
}
