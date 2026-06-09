<?php

namespace Pushword\Admin\Tests\Controller;

use Pushword\Admin\Tests\AbstractAdminTestClass;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class MediaTableViewTest extends AbstractAdminTestClass
{
    public function testTableViewRendersEditableRows(): void
    {
        $client = $this->loginUser();
        $client->catchExceptions(false);

        // Ensure at least one media exists to render a row.
        $crawler = $client->request(Request::METHOD_GET, '/admin/multi-upload');
        $csrfToken = $crawler->filter('#pw-multi-upload')->attr('data-csrf-token');

        $tempFile = sys_get_temp_dir().'/table-view.jpg';
        $img = imagecreatetruecolor(12, 8);
        imagejpeg($img, $tempFile);

        $client->request(Request::METHOD_POST, '/admin/multi-upload/upload', [
            '_token' => $csrfToken,
            'originalHash' => sha1_file($tempFile),
        ], ['file' => new UploadedFile($tempFile, 'table-view.jpg', 'image/jpeg', null, true)]);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $crawler = $client->request(Request::METHOD_GET, '/admin/media?view=table');

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        self::assertGreaterThan(0, $crawler->filter('#pw-media-table')->count(), 'Table-view wrapper should render');
        self::assertGreaterThan(0, $crawler->filter('.pw-m-name')->count(), 'Editable name field should render');
        self::assertGreaterThan(0, $crawler->filter('input[data-field="tags"]')->count(), 'Editable tags field should render');
        self::assertGreaterThan(0, $crawler->filter('img.pw-thumb[data-full-src]')->count(), 'Viewable thumbnail should render');
    }
}
