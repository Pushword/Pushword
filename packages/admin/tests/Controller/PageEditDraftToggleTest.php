<?php

declare(strict_types=1);

namespace Pushword\Admin\Tests\Controller;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Repository\PageRepository;
use Symfony\Component\HttpFoundation\Request;

#[Group('integration')]
final class PageEditDraftToggleTest extends AbstractAdminTestClass
{
    public function testTheDateReadoutIsFormattedWithTheAdminLocale(): void
    {
        $client = $this->loginUser();

        /** @var PageRepository $pageRepo */
        $pageRepo = self::getContainer()->get(PageRepository::class);
        $page = $pageRepo->findOneBy(['slug' => 'homepage', 'host' => 'localhost.dev']);
        self::assertNotNull($page, 'Fixture page "homepage" must exist');

        $crawler = $client->request(Request::METHOD_GET, $this->generateAdminUrl('admin_page_edit', ['id' => $page->id]));
        self::assertResponseIsSuccessful();

        $html = (string) $client->getResponse()->getContent();
        $locale = $client->getRequest()->getLocale();
        self::assertNotSame('', $locale);

        // The readout used to call toLocaleString('fr-FR'), so an English admin was
        // shown 15/09/2026 next to the draft switch.
        self::assertStringNotContainsString("'fr-FR'", $html);
        self::assertStringContainsString("const adminLocale = '".$locale."'", $html);

        self::assertCount(1, $crawler->filter('#pw-draft-toggle-status'));
    }
}
