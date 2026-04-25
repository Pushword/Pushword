<?php

declare(strict_types=1);

namespace Pushword\Admin\Tests\Controller;

use Pushword\Admin\Tests\AbstractAdminTestClass;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class MediaMultiUploadTest extends AbstractAdminTestClass
{
    public function testDuplicateJpgIsSkipped(): void
    {
        $this->assertDuplicateIsSkipped('test-dup.jpg', 'image/jpeg');
    }

    public function testDuplicatePngIsSkipped(): void
    {
        $this->assertDuplicateIsSkipped('test-dup.png', 'image/png');
    }

    private function assertDuplicateIsSkipped(string $fileName, string $mimeType): void
    {
        $client = $this->loginUser();
        $client->catchExceptions(false);

        // Load the multi-upload page to get CSRF token
        $crawler = $client->request(Request::METHOD_GET, '/admin/multi-upload');
        $csrfToken = $crawler->filter('#pw-multi-upload')->attr('data-csrf-token');

        $tempFile = $this->createTempImage($fileName, $mimeType);
        $originalHash = sha1_file($tempFile);

        // First upload — should succeed
        $file1 = new UploadedFile($tempFile, $fileName, $mimeType, null, true);
        $client->request(Request::METHOD_POST, '/admin/multi-upload/upload', [
            '_token' => $csrfToken,
            'originalHash' => $originalHash,
        ], ['file' => $file1]);

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        /** @var array<string, mixed> $data1 */
        $data1 = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertArrayNotHasKey('skipped', $data1, 'First upload should not be skipped');
        self::assertArrayHasKey('id', $data1);

        // Second upload of the same content — should be skipped
        $tempFile2 = $this->createTempImage($fileName, $mimeType);
        $originalHash2 = sha1_file($tempFile2);
        self::assertSame($originalHash, $originalHash2, 'Same image content should produce same hash');

        $file2 = new UploadedFile($tempFile2, $fileName, $mimeType, null, true);
        $client->request(Request::METHOD_POST, '/admin/multi-upload/upload', [
            '_token' => $csrfToken,
            'originalHash' => $originalHash2,
        ], ['file' => $file2]);

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        /** @var array<string, mixed> $data2 */
        $data2 = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertTrue($data2['skipped'] ?? false, 'Second upload of same content should be skipped');
        self::assertSame($data1['id'], $data2['id'], 'Skipped response should reference the original media');
    }

    private function createTempImage(string $fileName, string $mimeType): string
    {
        $tempFile = sys_get_temp_dir().'/'.$fileName;
        $img = imagecreatetruecolor(10, 10);
        $color = imagecolorallocate($img, 255, 0, 0);
        \assert(false !== $color);
        imagefilledrectangle($img, 0, 0, 9, 9, $color);

        if ('image/png' === $mimeType) {
            imagepng($img, $tempFile);
        } else {
            imagejpeg($img, $tempFile);
        }

        return $tempFile;
    }
}
