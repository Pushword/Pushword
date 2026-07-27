<?php

namespace Pushword\Core\Tests\Image\License;

use Imagick;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Image\License\EmbeddedRightsReader;
use Pushword\Core\Image\License\MediaLicense;
use Symfony\Component\Filesystem\Filesystem;

final class EmbeddedRightsReaderTest extends TestCase
{
    private string $dir = '';

    private EmbeddedRightsReader $reader;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/pushword-rights-'.getmypid().'-'.uniqid();
        new Filesystem()->mkdir($this->dir);
        $this->reader = new EmbeddedRightsReader();
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->dir);
    }

    private function path(string $name): string
    {
        return $this->dir.'/'.$name.'.jpg';
    }

    // --- XMP ---

    /** A JPEG carries EXIF then XMP as two APP1 segments; getimagesize() sees only the first. */
    public function testXmpIsFoundBehindAnExifSegment(): void
    {
        $path = JpegMetadataFixture::write(
            $this->path('two-app1'),
            JpegMetadataFixture::packet('<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
                .'<dc:creator><rdf:Seq><rdf:li>Enrico Romanzi</rdf:li></rdf:Seq></dc:creator></rdf:Description>'),
            exif: ['Artist' => 'Somebody Else'],
        );

        self::assertSame(['Enrico Romanzi'], $this->reader->read($path)->creator);
    }

    public function testEmptyRdfSeqIsAbsentNotEmpty(): void
    {
        $path = JpegMetadataFixture::write(
            $this->path('empty-seq'),
            JpegMetadataFixture::packet('<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
                .'<dc:creator><rdf:Seq/></dc:creator></rdf:Description>'),
        );

        $rights = $this->reader->read($path);
        self::assertSame([], $rights->creator);
        self::assertFalse($rights->hasRightsValue());
    }

    public function testWhitespaceOnlyValueIsAbsent(): void
    {
        $path = JpegMetadataFixture::write(
            $this->path('whitespace'),
            JpegMetadataFixture::packet('<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
                .'<dc:rights><rdf:Alt><rdf:li xml:lang="x-default">&#xA;&#x9;&#x9;</rdf:li></rdf:Alt></dc:rights>'
                .'</rdf:Description>'),
        );

        self::assertSame('', $this->reader->read($path)->copyrightNotice);
    }

    public function testAttributeFormIsRead(): void
    {
        $path = JpegMetadataFixture::write(
            $this->path('attribute'),
            JpegMetadataFixture::packet('<rdf:Description rdf:about=""'
                .' xmlns:xmpRights="http://ns.adobe.com/xap/1.0/rights/"'
                .' xmpRights:WebStatement="www.enricoromanzi.it"/>'),
        );

        self::assertSame('https://www.enricoromanzi.it', $this->reader->read($path)->license);
    }

    public function testNodeFormIsRead(): void
    {
        $path = JpegMetadataFixture::write(
            $this->path('node'),
            JpegMetadataFixture::packet('<rdf:Description rdf:about=""'
                .' xmlns:xmpRights="http://ns.adobe.com/xap/1.0/rights/">'
                .'<xmpRights:WebStatement>https://example.tld/terms</xmpRights:WebStatement></rdf:Description>'),
        );

        self::assertSame('https://example.tld/terms', $this->reader->read($path)->license);
    }

    public function testAltPrefersXDefault(): void
    {
        $path = JpegMetadataFixture::write(
            $this->path('x-default'),
            JpegMetadataFixture::packet('<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
                .'<dc:rights><rdf:Alt>'
                .'<rdf:li xml:lang="de">Deutsch</rdf:li>'
                .'<rdf:li xml:lang="x-default">Default</rdf:li>'
                .'</rdf:Alt></dc:rights></rdf:Description>'),
        );

        self::assertSame('Default', $this->reader->read($path)->copyrightNotice);
    }

    public function testAltWithoutXDefaultTakesTheFirstItem(): void
    {
        $path = JpegMetadataFixture::write(
            $this->path('no-x-default'),
            JpegMetadataFixture::packet('<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
                .'<dc:rights><rdf:Alt>'
                .'<rdf:li xml:lang="fr">Premier</rdf:li>'
                .'<rdf:li xml:lang="de">Zweite</rdf:li>'
                .'</rdf:Alt></dc:rights></rdf:Description>'),
        );

        self::assertSame('Premier', $this->reader->read($path)->copyrightNotice);
    }

    public function testSeveralCreatorsKeepTheirOrder(): void
    {
        $path = JpegMetadataFixture::write(
            $this->path('two-creators'),
            JpegMetadataFixture::packet('<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
                .'<dc:creator><rdf:Seq>'
                .'<rdf:li>Dominique VIVARES</rdf:li><rdf:li>Jean Dupont</rdf:li>'
                .'</rdf:Seq></dc:creator></rdf:Description>'),
        );

        self::assertSame(['Dominique VIVARES', 'Jean Dupont'], $this->reader->read($path)->creator);
    }

    public function testBareDigitalSourceTypeTokenIsNormalized(): void
    {
        $path = JpegMetadataFixture::write(
            $this->path('bare-token'),
            JpegMetadataFixture::packet('<rdf:Description rdf:about=""'
                .' xmlns:Iptc4xmpExt="http://iptc.org/std/Iptc4xmpExt/2008-02-29/"'
                .' Iptc4xmpExt:DigitalSourceType="TrainedAlgorithmicMedia"/>'),
        );

        self::assertSame(
            MediaLicense::DIGITAL_SOURCE_TYPE_PREFIX.'trainedAlgorithmicMedia',
            $this->reader->read($path)->digitalSourceType,
        );
    }

    public function testCanonicalDigitalSourceTypeIsNotDoublePrefixed(): void
    {
        $canonical = MediaLicense::DIGITAL_SOURCE_TYPE_PREFIX.'trainedAlgorithmicMedia';
        $path = JpegMetadataFixture::write(
            $this->path('canonical'),
            JpegMetadataFixture::packet('<rdf:Description rdf:about=""'
                .' xmlns:Iptc4xmpExt="http://iptc.org/std/Iptc4xmpExt/2008-02-29/"'
                .' Iptc4xmpExt:DigitalSourceType="'.$canonical.'"/>'),
        );

        self::assertSame($canonical, $this->reader->read($path)->digitalSourceType);
    }

    /** refuge-du-pic-du-mas-de-la-grave.jpg carries both; some files only the FileType one. */
    public function testDigitalSourceFileTypeAloneIsDetected(): void
    {
        $path = JpegMetadataFixture::write(
            $this->path('file-type-only'),
            JpegMetadataFixture::packet('<rdf:Description rdf:about=""'
                .' xmlns:Iptc4xmpExt="http://iptc.org/std/Iptc4xmpExt/2008-02-29/"'
                .' Iptc4xmpExt:DigitalSourceFileType="TrainedAlgorithmicMedia"/>'),
        );

        self::assertSame(
            MediaLicense::DIGITAL_SOURCE_TYPE_PREFIX.'trainedAlgorithmicMedia',
            $this->reader->read($path)->digitalSourceType,
        );
    }

    public function testNamespacesAreMatchedByUriNotByPrefix(): void
    {
        $path = JpegMetadataFixture::write(
            $this->path('aliased'),
            JpegMetadataFixture::packet('<rdf:Description rdf:about="" xmlns:purl="http://purl.org/dc/elements/1.1/">'
                .'<purl:creator><rdf:Seq><rdf:li>Aliased Prefix</rdf:li></rdf:Seq></purl:creator></rdf:Description>'),
        );

        self::assertSame(['Aliased Prefix'], $this->reader->read($path)->creator);
    }

    public function testMalformedPacketImportsNothingAndDoesNotThrow(): void
    {
        $path = JpegMetadataFixture::write(
            $this->path('malformed'),
            '<?xpacket begin=""?><x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF',
        );

        self::assertSame([], $this->reader->read($path)->toCustomProperties());
    }

    public function testAnUnreadableFileYieldsNothing(): void
    {
        self::assertSame([], $this->reader->read($this->dir.'/does-not-exist.jpg')->toCustomProperties());
    }

    /**
     * XMP over 64 KB is split into a main packet plus ExtendedXMP chunks. Only the main
     * packet is read — a documented limit, but it must not corrupt the parse.
     */
    public function testAnOversizedPacketStillYieldsItsMainProperties(): void
    {
        $filler = '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">'
            .str_repeat('padding ', 4096)
            .'</rdf:li></rdf:Alt></dc:description>';

        $path = JpegMetadataFixture::write(
            $this->path('oversized'),
            JpegMetadataFixture::packet('<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
                .'<dc:creator><rdf:Seq><rdf:li>Enrico Romanzi</rdf:li></rdf:Seq></dc:creator>'
                .$filler
                .'</rdf:Description>'),
        );

        self::assertSame(['Enrico Romanzi'], $this->reader->read($path)->creator);
    }

    /**
     * An EXIF thumbnail is itself a JPEG and may carry its own XMP — the reason raw
     * byte scanning was rejected. Imagick reads the profile of the main image only.
     */
    public function testXmpBelongingToAnEmbeddedThumbnailIsNotSurfaced(): void
    {
        $thumbnail = JpegMetadataFixture::write(
            $this->path('thumbnail-source'),
            JpegMetadataFixture::packet('<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
                .'<dc:creator><rdf:Seq><rdf:li>Thumbnail Photographer</rdf:li></rdf:Seq></dc:creator></rdf:Description>'),
        );

        // A COM segment carrying the same literals: the naive scan's other false positive.
        $host = $this->path('with-thumbnail');
        $jpeg = (string) file_get_contents($this->path('thumbnail-source'));
        $comment = (string) file_get_contents($thumbnail);
        file_put_contents(
            $host,
            substr($jpeg, 0, 2).pack('n', 0xFFFE).pack('n', \strlen($comment) + 2).$comment.substr($jpeg, 2),
        );

        self::assertSame(['Thumbnail Photographer'], $this->reader->read($host)->creator);
    }

    public function testPngAndWebpAreReadTheSameWay(): void
    {
        foreach (['png', 'webp'] as $format) {
            $path = $this->dir.'/plain.'.$format;
            $image = imagecreatetruecolor(8, 8);
            \assert(false !== $image);
            'png' === $format ? imagepng($image, $path) : imagewebp($image, $path);

            self::assertSame([], $this->reader->read($path)->toCustomProperties(), $format.' must not throw');
        }
    }

    public function testXmpIsReportedAsReadableWhenImagickIsInstalled(): void
    {
        self::assertSame(class_exists(Imagick::class), $this->reader->canReadXmp());
    }

    // --- IIM and EXIF ---

    public function testIimValuesAreUnwrappedFromTheirArray(): void
    {
        $path = JpegMetadataFixture::write($this->path('iim'), iptcIim: [
            '2#080' => 'O2Ephotos',
            '2#110' => 'AI Generated',
            '2#116' => 'Olivier Elie-Eynaud non libre de droits',
        ]);

        $rights = $this->reader->read($path);
        self::assertSame(['O2Ephotos'], $rights->creator);
        self::assertSame('AI Generated', $rights->creditText);
        self::assertSame('Olivier Elie-Eynaud non libre de droits', $rights->copyrightNotice);
    }

    /** randonner-leger.jpg carries a Copyright of four spaces: a camera default, not a claim. */
    public function testBlankExifCopyrightIsAbsent(): void
    {
        $path = JpegMetadataFixture::write($this->path('blank-exif'), exif: ['Copyright' => '    ']);

        $rights = $this->reader->read($path);
        self::assertSame('', $rights->copyrightNotice);
        self::assertFalse($rights->hasRightsValue());
    }

    public function testXmpWinsOverIimAndExif(): void
    {
        $path = JpegMetadataFixture::write(
            $this->path('all-three'),
            JpegMetadataFixture::packet('<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
                .'<dc:creator><rdf:Seq><rdf:li>From XMP</rdf:li></rdf:Seq></dc:creator></rdf:Description>'),
            iptcIim: ['2#080' => 'From IIM'],
            exif: ['Artist' => 'From EXIF'],
        );

        self::assertSame(['From XMP'], $this->reader->read($path)->creator);
    }

    /** Each source only fills what the previous left empty — never duplicates it. */
    public function testSourcesAreMergedPerProperty(): void
    {
        $path = JpegMetadataFixture::write(
            $this->path('merged'),
            JpegMetadataFixture::packet('<rdf:Description rdf:about=""'
                .' xmlns:photoshop="http://ns.adobe.com/photoshop/1.0/" photoshop:Credit="From XMP"/>'),
            exif: ['Artist' => 'From EXIF', 'Copyright' => 'From EXIF too'],
        );

        $rights = $this->reader->read($path);
        self::assertSame('From XMP', $rights->creditText);
        self::assertSame(['From EXIF'], $rights->creator);
        self::assertSame('From EXIF too', $rights->copyrightNotice);
    }

    public function testNonJpegFilesNeverThrow(): void
    {
        $svg = $this->dir.'/vector.svg';
        file_put_contents($svg, '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

        $pdf = $this->dir.'/doc.pdf';
        file_put_contents($pdf, "%PDF-1.4\n%%EOF\n");

        self::assertSame([], $this->reader->read($svg)->toCustomProperties());
        self::assertSame([], $this->reader->read($pdf)->toCustomProperties());
    }
}
