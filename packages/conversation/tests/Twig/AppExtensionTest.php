<?php

declare(strict_types=1);

namespace Pushword\Conversation\Tests\Twig;

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

        self::assertStringContainsString('/conversation/ms-message/', $url);
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
}
