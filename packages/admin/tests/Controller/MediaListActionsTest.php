<?php

declare(strict_types=1);

namespace Pushword\Admin\Tests\Controller;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
final class MediaListActionsTest extends AbstractAdminTestClass
{
    public function testRowActionsLiveInASingleDropdown(): void
    {
        $crawler = $this->mediaListCrawler();

        $rows = $crawler->filter('#pw-media-table tbody tr[data-id]')->count();
        self::assertGreaterThan(0, $rows, 'At least one media must render a row');

        // One quiet toggle per row, both actions behind it.
        self::assertCount($rows, $crawler->filter('.pw-row-menu .dropdown-toggle'));
        self::assertCount($rows, $crawler->filter('.pw-row-menu .dropdown-menu .action-edit'));
        self::assertCount($rows, $crawler->filter('.pw-row-menu .dropdown-menu .action-delete'));

        // Delete used to be a red button standing at full strength on every row.
        self::assertCount(0, $crawler->filter('#pw-media-table .btn-outline-danger'));
        self::assertCount(0, $crawler->filter('#pw-media-table td.pw-row-actions > .btn'));
    }

    public function testDeleteKeepsItsJavascriptHooksInsideTheDropdown(): void
    {
        $crawler = $this->mediaListCrawler();

        $delete = $crawler->filter('.pw-row-menu .dropdown-menu .pw-delete-btn')->first();
        self::assertCount(1, $delete);
        self::assertStringContainsString('dropdown-item', (string) $delete->attr('class'));
        // admin.mediaInlineEdit.js reads both attributes off this button.
        self::assertNotEmpty($delete->attr('data-id'));
        self::assertNotEmpty($delete->attr('data-confirm'));
    }

    public function testHeaderKeepsASinglePrimaryButton(): void
    {
        $crawler = $this->mediaListCrawler();

        $primaries = $crawler->filter('.content-header .btn-primary')
            ->each(static fn (Crawler $node): string => trim($node->text()));

        self::assertCount(1, $primaries, 'Two filled buttons leave no way to tell which action is the default');
    }

    private function mediaListCrawler(): Crawler
    {
        $client = $this->loginUser();

        // The list only renders row actions when it has a row.
        $crawler = $client->request(Request::METHOD_GET, '/admin/multi-upload');
        $csrfToken = $crawler->filter('#pw-multi-upload')->attr('data-csrf-token');

        $tempFile = sys_get_temp_dir().'/row-actions.jpg';
        imagejpeg(imagecreatetruecolor(12, 8), $tempFile);

        $client->request(Request::METHOD_POST, '/admin/multi-upload/upload', [
            '_token' => $csrfToken,
            'originalHash' => sha1_file($tempFile),
        ], ['file' => new UploadedFile($tempFile, 'row-actions.jpg', 'image/jpeg', null, true)]);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $crawler = $client->request(Request::METHOD_GET, '/admin/media?view=table');
        self::assertResponseIsSuccessful();

        return $crawler;
    }
}
