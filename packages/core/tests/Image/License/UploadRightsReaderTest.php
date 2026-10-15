<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Image\License;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Image\License\EmbeddedRightsReader;
use Pushword\Core\Image\License\UploadRightsReader;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * What an upload will import, readable before the upload is persisted — the seed
 * listener decides from it, and an app constraint can judge the same thing.
 */
final class UploadRightsReaderTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/pushword-upload-rights-'.getmypid().'-'.uniqid();
        new Filesystem()->mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->dir);
    }

    private function read(string $path, string $supplied = ''): string
    {
        $request = new Request(request: '' === $supplied ? [] : ['embeddedMetadata' => $supplied]);
        $reader = new UploadRightsReader(new EmbeddedRightsReader(), new RequestStack([$request]));

        return implode(', ', $reader->read(new File($path))->creator);
    }

    private function creatorXmp(string $name): string
    {
        return ImageMetadataFixture::packet(
            '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
            .'<dc:creator><rdf:Seq><rdf:li>'.$name.'</rdf:li></rdf:Seq></dc:creator></rdf:Description>',
        );
    }

    private function supplied(string $xmp): string
    {
        return json_encode(['xmp' => base64_encode($xmp)], \JSON_THROW_ON_ERROR);
    }

    /** The admin's canvas strips the file, so its metadata arrives beside it. */
    public function testTheSidecarFillsAStrippedFile(): void
    {
        $path = ImageMetadataFixture::write($this->dir.'/stripped.jpg');

        self::assertSame('Enrico Romanzi', $this->read($path, $this->supplied($this->creatorXmp('Enrico Romanzi'))));
    }

    public function testTheStoredBytesOutrankTheSidecar(): void
    {
        $path = ImageMetadataFixture::write($this->dir.'/carried.jpg', $this->creatorXmp('Enrico Romanzi'));

        self::assertSame('Enrico Romanzi', $this->read($path, $this->supplied($this->creatorXmp('Somebody Else'))));
    }

    public function testWithoutASidecarOnlyTheFileSpeaks(): void
    {
        $path = ImageMetadataFixture::write($this->dir.'/plain.jpg');

        self::assertSame('', $this->read($path));
    }
}
