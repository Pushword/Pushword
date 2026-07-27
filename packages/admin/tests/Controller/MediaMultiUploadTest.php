<?php

namespace Pushword\Admin\Tests\Controller;

use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Image\License\MediaLicense;
use Pushword\Core\Tests\Image\License\ImageMetadataFixture;
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

    /**
     * The upload hook now imports the rights a file carries, so a row whose file
     * claims third-party rights has to disclose them — hiding a photographer's name
     * in an invisible field would be worse than storing nothing.
     */
    public function testUploadDisclosesImportedThirdPartyRights(): void
    {
        $client = $this->loginUser();
        $client->catchExceptions(false);

        $crawler = $client->request(Request::METHOD_GET, '/admin/multi-upload');
        $csrfToken = $crawler->filter('#pw-multi-upload')->attr('data-csrf-token');
        self::assertNotEmpty($crawler->filter('#pw-multi-upload')->attr('data-license-labels'));

        $fileName = 'test-rights-'.uniqid().'.jpg';
        $tempFile = ImageMetadataFixture::write(
            sys_get_temp_dir().'/'.$fileName,
            ImageMetadataFixture::packet(
                '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
                .'<dc:creator><rdf:Seq><rdf:li>Enrico Romanzi</rdf:li></rdf:Seq></dc:creator></rdf:Description>',
            ),
        );

        $client->request(Request::METHOD_POST, '/admin/multi-upload/upload', [
            '_token' => $csrfToken,
            'originalHash' => sha1_file($tempFile),
        ], ['file' => new UploadedFile($tempFile, $fileName, 'image/jpeg', null, true)]);

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        /** @var array<string, mixed> $data */
        $data = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertSame(MediaLicense::STATE_THIRD_PARTY, $data['licenseState']);
        self::assertIsArray($data['license']);
        // One input per key in a row, so the creators collapse to their compact form.
        // A file gives bare names, hence Person.
        self::assertSame('Enrico Romanzi (Person)', $data['license'][MediaLicense::CREATOR]);

        $mediaId = $data['id'];
        self::assertIsInt($mediaId);

        // The row's inline editing has to reach the license keys, not just alt/tags.
        $client->request(Request::METHOD_POST, '/admin/media/'.$mediaId.'/inline-update', [
            '_token' => $csrfToken,
            'field' => MediaLicense::CREDIT_TEXT,
            'value' => 'Enrico Romanzi photos',
        ]);

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        /** @var array<string, mixed> $updated */
        $updated = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertTrue($updated['success']);
        self::assertSame(MediaLicense::STATE_OVERRIDDEN, $updated['licenseState']);
    }

    /** The other 99 % of rows: nothing to disclose, nothing rendered. */
    public function testUploadOfAPlainImageDisclosesNothing(): void
    {
        $client = $this->loginUser();
        $client->catchExceptions(false);

        $crawler = $client->request(Request::METHOD_GET, '/admin/multi-upload');
        $csrfToken = $crawler->filter('#pw-multi-upload')->attr('data-csrf-token');

        $fileName = 'test-plain-'.uniqid().'.jpg';
        $tempFile = ImageMetadataFixture::write(sys_get_temp_dir().'/'.$fileName);

        $client->request(Request::METHOD_POST, '/admin/multi-upload/upload', [
            '_token' => $csrfToken,
            'originalHash' => sha1_file($tempFile),
        ], ['file' => new UploadedFile($tempFile, $fileName, 'image/jpeg', null, true)]);

        /** @var array<string, mixed> $data */
        $data = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertNotSame(MediaLicense::STATE_THIRD_PARTY, $data['licenseState']);
        self::assertIsArray($data['license']);
        self::assertSame('', $data['license'][MediaLicense::CREATOR]);
    }

    /**
     * What the browser actually posts: the file scaled down through a canvas, which
     * keeps no metadata, and the segments it lifted out beforehand beside it.
     *
     * The listener reads them off the current request, so nothing proves the endpoint
     * carries them except going through the endpoint.
     */
    public function testMetadataPostedBesideAStrippedFileIsImported(): void
    {
        $client = $this->loginUser();
        $client->catchExceptions(false);

        $crawler = $client->request(Request::METHOD_GET, '/admin/multi-upload');
        $csrfToken = $crawler->filter('#pw-multi-upload')->attr('data-csrf-token');

        $fileName = 'test-sidecar-'.uniqid().'.jpg';
        $tempFile = ImageMetadataFixture::write(sys_get_temp_dir().'/'.$fileName);
        // Unique bytes past the EOI: a genuinely stripped upload differs from every
        // other because its pixels were re-encoded, and a fixture repeating another
        // test's bytes is deduplicated by hash before the listener ever runs.
        file_put_contents($tempFile, uniqid(), \FILE_APPEND);

        $client->request(Request::METHOD_POST, '/admin/multi-upload/upload', [
            '_token' => $csrfToken,
            'originalHash' => sha1_file($tempFile),
            'embeddedMetadata' => json_encode([
                'xmp' => base64_encode(ImageMetadataFixture::packet(
                    '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
                    .'<dc:creator><rdf:Seq><rdf:li>Enrico Romanzi</rdf:li></rdf:Seq></dc:creator></rdf:Description>',
                )),
            ], \JSON_THROW_ON_ERROR),
        ], ['file' => new UploadedFile($tempFile, $fileName, 'image/jpeg', null, true)]);

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        /** @var array<string, mixed> $data */
        $data = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertSame(MediaLicense::STATE_THIRD_PARTY, $data['licenseState']);
        self::assertIsArray($data['license']);
        self::assertSame('Enrico Romanzi (Person)', $data['license'][MediaLicense::CREATOR]);
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
