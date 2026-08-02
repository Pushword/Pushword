<?php

namespace Pushword\Conversation\Tests\Twig;

use Exception;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Conversation\Twig\AppExtension;
use Pushword\Core\Entity\Page;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class AppExtensionTest extends KernelTestCase
{
    private function getExtensionWithPage(string $host = 'localhost.dev', string $slug = 'test-page'): AppExtension
    {
        self::bootKernel();

        $siteRegistry = self::getContainer()->get(SiteRegistry::class);

        $page = new Page();
        $page->host = $host;
        $page->slug = $slug;
        $page->locale = 'en';

        $siteRegistry->setCurrentPage($page);

        return self::getContainer()->get(AppExtension::class);
    }

    public function testGetConversationRouteDefaultReferring(): void
    {
        $ext = $this->getExtensionWithPage('localhost.dev', 'test-page');

        $url = $ext->getConversationRoute('ms-message');

        // Must be absolute AND prefixed with exactly the page's base_live_url: a
        // statically served page has no PHP, so a relative route would 404 against
        // its own origin. Asserting the exact prefix guards against a wrong prefix
        // or the double-prefix that conversationFormBtn used to add on top.
        $baseLiveUrl = self::getContainer()->get(SiteRegistry::class)->get('localhost.dev')->getStr('base_live_url');
        self::assertNotSame('', $baseLiveUrl);
        self::assertStringStartsWith($baseLiveUrl.'/conversation/ms-message/', $url);
        self::assertStringContainsString('ms-message_localhost.dev/test-page', $url);
        self::assertStringContainsString('host=localhost.dev', $url);
        self::assertStringContainsString('locale=en', $url);
    }

    public function testGetConversationRouteCustomReferring(): void
    {
        $ext = $this->getExtensionWithPage('localhost.dev', 'test-page');

        $url = $ext->getConversationRoute('ms-message', 'custom-ref');

        self::assertStringContainsString('custom-ref_localhost.dev/test-page', $url);
    }

    public function testConversationFormBtnRendersHtml(): void
    {
        $ext = $this->getExtensionWithPage();

        $twig = self::getContainer()->get('twig');
        $html = $ext->conversationFormBtn($twig, 'Contact us');

        self::assertStringContainsString('Contact us', $html);
    }

    public function testConversationFormBtnWithCustomReferring(): void
    {
        $ext = $this->getExtensionWithPage('localhost.dev', 'my-page');

        $twig = self::getContainer()->get('twig');
        $html = $ext->conversationFormBtn($twig, 'Ask', 'ms-message', 'link-btn', 'question');

        self::assertStringContainsString('Ask', $html);
    }

    public function testShowConversationRendersTheMessagesListView(): void
    {
        $ext = $this->getExtensionWithPage();

        $html = $ext->showConversation(self::getContainer()->get('twig'), 'ms-message_localhost.dev/test-page');

        // No message published against that referring, so the list renders empty
        // rather than erroring — that is what a page with no comment yet looks like.
        self::assertSame('', trim($html));
    }

    /** A tag is spelled several ways in templates; each resolves to the same lookup. */
    public function testReviewsCountAcceptsEveryTagSpelling(): void
    {
        $ext = $this->getExtensionWithPage('localhost.dev', 'a-page-without-review');

        $page = new Page();
        $page->slug = 'a-tag-nobody-uses';

        self::assertSame(0, $ext->count('a-tag-nobody-uses'));
        self::assertSame(0, $ext->count([' a-tag-nobody-uses ']));
        self::assertSame(0, $ext->count($page));
    }

    /**
     * '#' asks for every review whatever its tag, so the empty tag list it produces
     * must reach the query — unlike the empty string, which counts nothing.
     */
    public function testHashCountsEveryReviewWhereAnEmptyTagCountsNone(): void
    {
        $ext = $this->getExtensionWithPage();

        self::assertGreaterThan(0, $ext->count('#'));
        self::assertSame(0, $ext->count(''));
        self::assertSame(0, $ext->count([]));
    }

    public function testReviewsCountFallsBackToTheCurrentPage(): void
    {
        self::assertSame(0, $this->getExtensionWithPage('localhost.dev', 'a-page-without-review')->count());
    }

    public function testReviewsCountNeedsAPageContext(): void
    {
        self::bootKernel();
        $ext = self::getContainer()->get(AppExtension::class);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageIsOrContains('No page or tag provided');

        $ext->count();
    }
}
