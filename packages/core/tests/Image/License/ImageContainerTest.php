<?php

namespace Pushword\Core\Tests\Image\License;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Image\License\ImageContainer;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The container walker reads attacker-supplied bytes: every length field in a JPEG
 * segment, a PNG chunk or a RIFF chunk comes from the uploaded file itself. These are
 * the malformed shapes, the happy paths being covered through EmbeddedRightsReaderTest.
 */
final class ImageContainerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/pw-container-'.bin2hex(random_bytes(6));
        new Filesystem()->mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->dir);
    }

    private function write(string $name, string $bytes): string
    {
        $path = $this->dir.'/'.$name;
        file_put_contents($path, $bytes);

        return $path;
    }

    public function testAMissingFileYieldsNothing(): void
    {
        $container = ImageContainer::read($this->dir.'/absent.png');

        self::assertSame('', $container->xmp);
        self::assertSame('', $container->c2pa);
    }

    public function testAnUnknownFormatYieldsNothing(): void
    {
        $container = ImageContainer::read($this->write('doc.pdf', "%PDF-1.4\n%%EOF\n"));

        self::assertSame('', $container->xmp);
        self::assertSame('', $container->c2pa);
    }

    public function testAnEmptyFileYieldsNothing(): void
    {
        self::assertSame('', ImageContainer::read($this->write('empty.png', ''))->xmp);
    }

    /** Only the magic bytes, then nothing — the walk must stop, not read past the end. */
    public function testATruncatedHeaderIsNotWalked(): void
    {
        self::assertSame('', ImageContainer::read($this->write('stub.png', "\x89PNG\r\n\x1A\n"))->xmp);
        self::assertSame('', ImageContainer::read($this->write('stub.jpg', "\xFF\xD8"))->xmp);
        self::assertSame('', ImageContainer::read($this->write('stub.webp', 'RIFF'.pack('V', 0).'WEBP'))->xmp);
    }

    /**
     * A chunk announcing four gigabytes inside a 40-byte file. Honouring the length
     * would allocate it; the walk has to notice it runs past the end.
     */
    public function testAChunkLongerThanTheFileIsNotAllocated(): void
    {
        $png = "\x89PNG\r\n\x1A\n".pack('N', 0xFFFFFFF).'caBX'.str_repeat('A', 16);
        self::assertSame('', ImageContainer::read($this->write('huge.png', $png))->c2pa);

        $webp = 'RIFF'.pack('V', 32).'WEBPXMP '.pack('V', 0xFFFFFFF).str_repeat('A', 16);
        self::assertSame('', ImageContainer::read($this->write('huge.webp', $webp))->xmp);
    }

    /** A JPEG segment length below its own two-byte field would rewind the walk. */
    public function testAnUnderlongJpegSegmentStopsTheWalk(): void
    {
        $jpeg = "\xFF\xD8\xFF\xE1".pack('n', 1)."\xFF\xD9";

        self::assertSame('', ImageContainer::read($this->write('short.jpg', $jpeg))->xmp);
    }

    /** A PNG text chunk with no null separator has no keyword to match. */
    public function testAPngTextChunkWithoutAKeywordIsIgnored(): void
    {
        $payload = 'no separator here';
        $png = "\x89PNG\r\n\x1A\n".pack('N', \strlen($payload)).'iTXt'.$payload.pack('N', 0);

        self::assertSame('', ImageContainer::read($this->write('nokw.png', $png))->xmp);
    }

    /** A deflate stream that is not one must yield nothing rather than warn. */
    public function testAPngTextChunkWithCorruptDeflateIsIgnored(): void
    {
        $payload = ImageMetadataFixture::PNG_XMP_KEYWORD."\x00\x01\x00\x00\x00".'not zlib at all';
        $png = "\x89PNG\r\n\x1A\n".pack('N', \strlen($payload)).'iTXt'.$payload.pack('N', 0);

        self::assertSame('', ImageContainer::read($this->write('badzip.png', $png))->xmp);
    }

    /** Odd-sized RIFF chunks are padded; missing the pad byte desynchronises the walk. */
    public function testOddSizedWebpChunksKeepTheWalkAligned(): void
    {
        $odd = 'x';
        $xmp = ImageMetadataFixture::packet('<rdf:Description rdf:about=""/>');
        $chunks = 'JUNK'.pack('V', \strlen($odd)).$odd."\x00"
            .'XMP '.pack('V', \strlen($xmp)).$xmp.(1 === \strlen($xmp) % 2 ? "\x00" : '');
        $webp = 'RIFF'.pack('V', \strlen($chunks) + 4).'WEBP'.$chunks;

        self::assertSame($xmp, ImageContainer::read($this->write('odd.webp', $webp))->xmp);
    }

    /** A JPEG hides its metadata before the scan; nothing after SOS is walkable. */
    public function testTheJpegWalkStopsAtTheScan(): void
    {
        $path = ImageMetadataFixture::write(
            $this->dir.'/scan.jpg',
            ImageMetadataFixture::packet('<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/"'
                .' dc:rights="Altimood"/>'),
        );

        self::assertStringContainsString('Altimood', ImageContainer::read($path)->xmp);
    }
}
