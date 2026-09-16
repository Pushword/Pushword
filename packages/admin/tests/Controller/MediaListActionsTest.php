<?php

declare(strict_types=1);

namespace Pushword\Admin\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Tests\Perf\QueryCountingTrait;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
final class MediaListActionsTest extends AbstractAdminTestClass
{
    use QueryCountingTrait;

    protected function tearDown(): void
    {
        $this->stopCountingQueries();
        parent::tearDown();
    }

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

    /**
     * The suggester's candidate list belongs to the table, not to each of its rows:
     * on a real media table (4.2k tags) repeating it cost ~77 KB of JSON per row.
     */
    public function testTheTagListIsEmittedOnceForTheWholeTable(): void
    {
        $crawler = $this->mediaListCrawler();

        $rows = $crawler->filter('#pw-media-table tbody tr[data-id]')->count();
        self::assertGreaterThan(0, $rows, 'At least one media must render a row');

        self::assertJson(
            (string) $crawler->filter('#pw-media-table')->attr('data-all-tags'),
            'the container carries the candidate list admin.tagsField.js reads',
        );

        $tagInputs = $crawler->filter('#pw-media-table .pw-m-tagsinput');
        self::assertCount($rows, $tagInputs, 'every row keeps its tag input');
        self::assertSame(
            array_fill(0, $rows, ''),
            $tagInputs->extract(['data-tags']),
            'data-tags stays as the marker, without the payload',
        );
    }

    /**
     * Mosaic is the default layout and has no tag input, so the list must cost
     * nothing there — it used to be computed on every media index.
     */
    public function testTheMosaicDefaultCarriesNoTagList(): void
    {
        $client = $this->loginUser();
        $crawler = $client->request(Request::METHOD_GET, '/admin/media?view=mosaic');
        self::assertResponseIsSuccessful();

        self::assertCount(0, $crawler->filter('[data-all-tags]'));
        self::assertCount(0, $crawler->filter('[data-tags]'));
    }

    public function testTheMosaicDefaultSkipsTheTagQueries(): void
    {
        $client = $this->loginUser();
        $client->disableReboot();
        $this->startCountingQueries(self::getContainer()->get(EntityManagerInterface::class)->getConnection());

        $mosaic = $this->countQueries(static function () use ($client): void {
            $client->request(Request::METHOD_GET, '/admin/media?view=mosaic');
        });
        $table = $this->countQueries(static function () use ($client): void {
            $client->request(Request::METHOD_GET, '/admin/media?view=table');
        });

        self::assertLessThan($table, $mosaic, 'the default layout must not pay for the tag list it never renders');
    }

    private function mediaListCrawler(): Crawler
    {
        $client = $this->loginUser();

        // Upload rather than lean on the media fixtures: they are known to go missing
        // when a worker is slowed (see MediaUsageTrackerTest), and a row is the whole
        // point of this class.
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
