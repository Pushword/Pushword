<?php

declare(strict_types=1);

namespace Pushword\Admin\Tests\Controller;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;

#[Group('integration')]
final class PageListActionsTest extends AbstractAdminTestClass
{
    public function testRowActionsLiveInASingleDropdown(): void
    {
        $client = $this->loginUser();
        $crawler = $client->request(Request::METHOD_GET, $this->generateAdminUrl('admin_page_list'));
        self::assertResponseIsSuccessful();

        $rows = $crawler->filter('.datagrid tbody tr[data-id]')->count();
        self::assertGreaterThan(0, $rows, 'Fixtures must provide at least one page');

        // One toggle per row, and every action behind it.
        self::assertCount($rows, $crawler->filter('.pw-page-actions .dropdown-toggle'));
        self::assertCount($rows, $crawler->filter('.pw-page-actions .dropdown-menu'));
        self::assertCount($rows, $crawler->filter('.pw-page-actions .dropdown-menu .action-edit'));

        // Every action is a dropdown entry; none keeps the old standalone button styling.
        $menu = $crawler->filter('.pw-page-actions .dropdown-menu')->first();
        self::assertGreaterThan(1, $menu->filter('.dropdown-item')->count());
        self::assertCount(0, $menu->filter('.btn'));
    }

    public function testColumnsAreOrderedTitleThenHoldThenWeight(): void
    {
        $client = $this->loginUser();
        $crawler = $client->request(Request::METHOD_GET, $this->generateAdminUrl('admin_page_list'));

        $headers = $crawler->filter('.datagrid thead th')->each(static fn (Crawler $th): string => trim($th->text()));
        $headers = array_values(array_filter($headers, static fn (string $h): bool => '' !== $h));

        self::assertSame(
            ['Published at', 'Title', 'Hold publication', 'Weight', 'Updated on', 'Actions'],
            $headers,
        );

        // The hold switch owns a cell now; it no longer sits above the tag input.
        self::assertCount(0, $crawler->filter('.pw-inline-tags-wrapper .pw-hold'));
        $holdCells = $crawler->filter('td[data-column="holdPublicationAt"]');
        self::assertGreaterThan(0, $holdCells->filter('.pw-hold')->count());
        self::assertStringContainsString('text-center', (string) $holdCells->first()->attr('class'));
    }

    public function testViewActionOpensEachPageOnItsOwnHost(): void
    {
        $client = $this->loginUser();
        $crawler = $client->request(Request::METHOD_GET, $this->generateAdminUrl('admin_page_list'));

        $rows = $crawler->filter('.datagrid tbody tr[data-id]');
        self::assertGreaterThan(0, $rows->count(), 'Fixtures must provide at least one page');

        $rows->each(static function (Crawler $row): void {
            // The title cell prints "<host> › <slug>"; the action must target that host.
            $host = trim(explode('›', $row->filter('.pw-page-inline a[target="_blank"]')->text())[0]);
            self::assertNotSame('', $host);

            $view = $row->filter('.dropdown-menu .action-viewPage');
            self::assertCount(1, $view, 'Every row offers the view action');
            self::assertSame('_blank', $view->attr('target'));
            self::assertSame('noopener', $view->attr('rel'));
            // Absolute and on the page's own host — a relative URL would open the
            // admin host instead of the site the page belongs to.
            self::assertStringStartsWith('http', (string) $view->attr('href'));
            self::assertStringContainsString($host, (string) $view->attr('href'));
        });
    }

    public function testDeleteKeepsItsConfirmationAttributesInsideTheDropdown(): void
    {
        $client = $this->loginUser();
        $crawler = $client->request(Request::METHOD_GET, $this->generateAdminUrl('admin_page_list'));

        $delete = $crawler->filter('.pw-page-actions .dropdown-menu .action-delete')->first();
        self::assertCount(1, $delete);
        self::assertStringContainsString('dropdown-item', (string) $delete->attr('class'));
        // Translated attributes must survive the dropdown rendering: passing the raw
        // TranslatableMessage through breaks the whole list with a 500.
        self::assertNotEmpty($delete->attr('data-action-confirmation-message'));
        self::assertSame('#modal-action-confirmation', $delete->attr('data-bs-target'));
    }

    public function testTitleLinksToTheEditFormWithoutTheLinkColour(): void
    {
        $client = $this->loginUser();
        $crawler = $client->request(Request::METHOD_GET, $this->generateAdminUrl('admin_page_list'));

        $title = $crawler->filter('.datagrid tbody tr[data-id] a.pw-page-title')->first();
        self::assertCount(1, $title);
        self::assertStringContainsString('/edit', (string) $title->attr('href'));
        self::assertNull($title->attr('style'), 'Title styling belongs to .pw-page-title, not to an inline style');
    }

    public function testThePageUrlIsOneLineKeepingItsEnd(): void
    {
        $client = $this->loginUser();
        $crawler = $client->request(Request::METHOD_GET, $this->generateAdminUrl('admin_page_list'));

        $url = $crawler->filter('.datagrid tbody tr[data-id] a.pw-page-url')->first();
        self::assertCount(1, $url);

        // A deep slug is cut at its start, which takes both spans: the outer one flips
        // direction so the ellipsis lands on the left, the inner one reads the path back
        // left to right. The whole URL stays reachable as the title.
        $slug = $url->filter('.pw-page-url__slug > span');
        self::assertCount(1, $slug);
        self::assertStringContainsString($slug->text(), (string) $url->attr('title'));
    }

    public function testTitleCellTextKeepsTheTableFontSize(): void
    {
        $client = $this->loginUser();
        $crawler = $client->request(Request::METHOD_GET, $this->generateAdminUrl('admin_page_list'));

        // Hierarchy inside the cell is carried by weight and colour. A percentage
        // size here compounds against the cell and lands off any type scale
        // (110% of 14px = 15.4px), so the text lines declare no size at all.
        $cell = $crawler->filter('.datagrid tbody tr[data-id] .pw-page-inline')->first();
        self::assertCount(1, $cell);

        foreach (['a.pw-page-title', 'a[target="_blank"]', '.pw-inline-tags-wrapper > span'] as $selector) {
            $node = $cell->filter($selector)->first();
            self::assertCount(1, $node, $selector.' should exist in the title cell');
            self::assertStringNotContainsString('font-size', (string) $node->attr('style'), $selector.' must not set a font size');
        }
    }
}
