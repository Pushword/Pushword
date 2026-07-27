<?php

namespace Pushword\Core\Tests\Image\License;

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
        $path = ImageMetadataFixture::write(
            $this->path('two-app1'),
            ImageMetadataFixture::packet('<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
                .'<dc:creator><rdf:Seq><rdf:li>Enrico Romanzi</rdf:li></rdf:Seq></dc:creator></rdf:Description>'),
            exif: ['Artist' => 'Somebody Else'],
        );

        self::assertSame(['Enrico Romanzi'], $this->reader->read($path)->creator);
    }

    public function testEmptyRdfSeqIsAbsentNotEmpty(): void
    {
        $path = ImageMetadataFixture::write(
            $this->path('empty-seq'),
            ImageMetadataFixture::packet('<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
                .'<dc:creator><rdf:Seq/></dc:creator></rdf:Description>'),
        );

        $rights = $this->reader->read($path);
        self::assertSame([], $rights->creator);
        self::assertFalse($rights->hasRightsValue());
    }

    public function testWhitespaceOnlyValueIsAbsent(): void
    {
        $path = ImageMetadataFixture::write(
            $this->path('whitespace'),
            ImageMetadataFixture::packet('<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
                .'<dc:rights><rdf:Alt><rdf:li xml:lang="x-default">&#xA;&#x9;&#x9;</rdf:li></rdf:Alt></dc:rights>'
                .'</rdf:Description>'),
        );

        self::assertSame('', $this->reader->read($path)->copyrightNotice);
    }

    public function testAttributeFormIsRead(): void
    {
        $path = ImageMetadataFixture::write(
            $this->path('attribute'),
            ImageMetadataFixture::packet('<rdf:Description rdf:about=""'
                .' xmlns:xmpRights="http://ns.adobe.com/xap/1.0/rights/"'
                .' xmpRights:WebStatement="www.enricoromanzi.it"/>'),
        );

        self::assertSame('https://www.enricoromanzi.it', $this->reader->read($path)->license);
    }

    public function testNodeFormIsRead(): void
    {
        $path = ImageMetadataFixture::write(
            $this->path('node'),
            ImageMetadataFixture::packet('<rdf:Description rdf:about=""'
                .' xmlns:xmpRights="http://ns.adobe.com/xap/1.0/rights/">'
                .'<xmpRights:WebStatement>https://example.tld/terms</xmpRights:WebStatement></rdf:Description>'),
        );

        self::assertSame('https://example.tld/terms', $this->reader->read($path)->license);
    }

    public function testAltPrefersXDefault(): void
    {
        $path = ImageMetadataFixture::write(
            $this->path('x-default'),
            ImageMetadataFixture::packet('<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
                .'<dc:rights><rdf:Alt>'
                .'<rdf:li xml:lang="de">Deutsch</rdf:li>'
                .'<rdf:li xml:lang="x-default">Default</rdf:li>'
                .'</rdf:Alt></dc:rights></rdf:Description>'),
        );

        self::assertSame('Default', $this->reader->read($path)->copyrightNotice);
    }

    public function testAltWithoutXDefaultTakesTheFirstItem(): void
    {
        $path = ImageMetadataFixture::write(
            $this->path('no-x-default'),
            ImageMetadataFixture::packet('<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
                .'<dc:rights><rdf:Alt>'
                .'<rdf:li xml:lang="fr">Premier</rdf:li>'
                .'<rdf:li xml:lang="de">Zweite</rdf:li>'
                .'</rdf:Alt></dc:rights></rdf:Description>'),
        );

        self::assertSame('Premier', $this->reader->read($path)->copyrightNotice);
    }

    public function testSeveralCreatorsKeepTheirOrder(): void
    {
        $path = ImageMetadataFixture::write(
            $this->path('two-creators'),
            ImageMetadataFixture::packet('<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
                .'<dc:creator><rdf:Seq>'
                .'<rdf:li>Dominique VIVARES</rdf:li><rdf:li>Jean Dupont</rdf:li>'
                .'</rdf:Seq></dc:creator></rdf:Description>'),
        );

        self::assertSame(['Dominique VIVARES', 'Jean Dupont'], $this->reader->read($path)->creator);
    }

    public function testBareDigitalSourceTypeTokenIsNormalized(): void
    {
        $path = ImageMetadataFixture::write(
            $this->path('bare-token'),
            ImageMetadataFixture::packet('<rdf:Description rdf:about=""'
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
        $path = ImageMetadataFixture::write(
            $this->path('canonical'),
            ImageMetadataFixture::packet('<rdf:Description rdf:about=""'
                .' xmlns:Iptc4xmpExt="http://iptc.org/std/Iptc4xmpExt/2008-02-29/"'
                .' Iptc4xmpExt:DigitalSourceType="'.$canonical.'"/>'),
        );

        self::assertSame($canonical, $this->reader->read($path)->digitalSourceType);
    }

    /** refuge-du-pic-du-mas-de-la-grave.jpg carries both; some files only the FileType one. */
    public function testDigitalSourceFileTypeAloneIsDetected(): void
    {
        $path = ImageMetadataFixture::write(
            $this->path('file-type-only'),
            ImageMetadataFixture::packet('<rdf:Description rdf:about=""'
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
        $path = ImageMetadataFixture::write(
            $this->path('aliased'),
            ImageMetadataFixture::packet('<rdf:Description rdf:about="" xmlns:purl="http://purl.org/dc/elements/1.1/">'
                .'<purl:creator><rdf:Seq><rdf:li>Aliased Prefix</rdf:li></rdf:Seq></purl:creator></rdf:Description>'),
        );

        self::assertSame(['Aliased Prefix'], $this->reader->read($path)->creator);
    }

    public function testMalformedPacketImportsNothingAndDoesNotThrow(): void
    {
        $path = ImageMetadataFixture::write(
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

        $path = ImageMetadataFixture::write(
            $this->path('oversized'),
            ImageMetadataFixture::packet('<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
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
        $thumbnail = ImageMetadataFixture::write(
            $this->path('thumbnail-source'),
            ImageMetadataFixture::packet('<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
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

    /**
     * The regression this suite missed for a whole release: extraction went through
     * Imagick::pingImage(), which exposes profiles for JPEG only, so every PNG and
     * WebP read as carrying nothing and got the site's licence seeded over whatever
     * their XMP actually claimed. The old test asserted "does not throw", which an
     * empty result satisfies.
     */
    public function testACreatorIsReadFromEveryContainerNotJustJpeg(): void
    {
        $packet = ImageMetadataFixture::packet(
            '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
            .'<dc:creator><rdf:Seq><rdf:li>Enrico Romanzi</rdf:li></rdf:Seq></dc:creator></rdf:Description>',
        );

        $files = [
            'jpeg' => ImageMetadataFixture::write($this->path('container'), $packet),
            'png' => ImageMetadataFixture::writePng($this->dir.'/container.png', $packet),
            'webp' => ImageMetadataFixture::writeWebp($this->dir.'/container.webp', $packet),
        ];

        foreach ($files as $format => $path) {
            self::assertSame(['Enrico Romanzi'], $this->reader->read($path)->creator, $format);
        }
    }

    /**
     * PNG has four ways to carry the packet and they all occur: the specified iTXt
     * keyword raw or deflated, the same keyword in a plain tEXt, and ImageMagick's
     * own hex-wrapped profile under a keyword of its own.
     */
    public function testEveryPngXmpShapeIsRead(): void
    {
        $packet = ImageMetadataFixture::packet(
            '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
            .'<dc:creator><rdf:Seq><rdf:li>Enrico Romanzi</rdf:li></rdf:Seq></dc:creator></rdf:Description>',
        );

        $shapes = [
            'iTXt' => ['iTXt', ImageMetadataFixture::PNG_XMP_KEYWORD, false],
            'iTXt deflated' => ['iTXt', ImageMetadataFixture::PNG_XMP_KEYWORD, true],
            'tEXt' => ['tEXt', ImageMetadataFixture::PNG_XMP_KEYWORD, false],
            // The chunk type decides the encoding, the keyword decides the meaning, so
            // the standard keyword has to work in a deflated chunk too.
            'zTXt' => ['zTXt', ImageMetadataFixture::PNG_XMP_KEYWORD, true],
            'raw profile' => ['zTXt', ImageMetadataFixture::PNG_RAW_PROFILE_KEYWORD, true],
            'raw profile in tEXt' => ['tEXt', ImageMetadataFixture::PNG_RAW_PROFILE_KEYWORD, false],
        ];

        foreach ($shapes as $label => [$chunkType, $keyword, $compressed]) {
            $path = ImageMetadataFixture::writePng(
                $this->dir.'/shape-'.md5($label).'.png',
                $packet,
                keyword: $keyword,
                chunkType: $chunkType,
                compressed: $compressed,
            );

            self::assertSame(['Enrico Romanzi'], $this->reader->read($path)->creator, $label);
        }
    }

    // --- IIM and EXIF ---

    public function testIimValuesAreUnwrappedFromTheirArray(): void
    {
        $path = ImageMetadataFixture::write($this->path('iim'), iptcIim: [
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
        $path = ImageMetadataFixture::write($this->path('blank-exif'), exif: ['Copyright' => '    ']);

        $rights = $this->reader->read($path);
        self::assertSame('', $rights->copyrightNotice);
        self::assertFalse($rights->hasRightsValue());
    }

    public function testXmpWinsOverIimAndExif(): void
    {
        $path = ImageMetadataFixture::write(
            $this->path('all-three'),
            ImageMetadataFixture::packet('<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
                .'<dc:creator><rdf:Seq><rdf:li>From XMP</rdf:li></rdf:Seq></dc:creator></rdf:Description>'),
            iptcIim: ['2#080' => 'From IIM'],
            exif: ['Artist' => 'From EXIF'],
        );

        self::assertSame(['From XMP'], $this->reader->read($path)->creator);
    }

    /** Each source only fills what the previous left empty — never duplicates it. */
    public function testSourcesAreMergedPerProperty(): void
    {
        $path = ImageMetadataFixture::write(
            $this->path('merged'),
            ImageMetadataFixture::packet('<rdf:Description rdf:about=""'
                .' xmlns:photoshop="http://ns.adobe.com/photoshop/1.0/" photoshop:Credit="From XMP"/>'),
            exif: ['Artist' => 'From EXIF', 'Copyright' => 'From EXIF too'],
        );

        $rights = $this->reader->read($path);
        self::assertSame('From XMP', $rights->creditText);
        self::assertSame(['From EXIF'], $rights->creator);
        self::assertSame('From EXIF too', $rights->copyrightNotice);
    }

    /**
     * @param array<string, string> $segments
     */
    private function supplied(array $segments): string
    {
        return json_encode(array_map(base64_encode(...), $segments), \JSON_THROW_ON_ERROR);
    }

    public function testEverySuppliedSegmentIsReadWithTheSameParsersAsAFile(): void
    {
        // The browser scales an image down through a canvas, which keeps no metadata,
        // so it posts the segments it lifted out first. Same bytes, no file.
        $rights = $this->reader->readSupplied($this->supplied([
            'xmp' => ImageMetadataFixture::packet(
                '<rdf:Description rdf:about="" xmlns:photoshop="http://ns.adobe.com/photoshop/1.0/">'
                .'<photoshop:Credit>Altimood</photoshop:Credit></rdf:Description>',
            ),
            'iptc' => ImageMetadataFixture::iimPayload(['2#080' => 'Enrico Romanzi']),
            'copyright' => '(c) Altimood',
            'c2pa' => ImageMetadataFixture::c2paActions(MediaLicense::DIGITAL_SOURCE_TYPE_PREFIX.MediaLicense::TRAINED_ALGORITHMIC_MEDIA),
        ]));

        self::assertSame('Altimood', $rights->creditText);
        self::assertSame(['Enrico Romanzi'], $rights->creator);
        self::assertSame('(c) Altimood', $rights->copyrightNotice);
        self::assertSame(
            MediaLicense::DIGITAL_SOURCE_TYPE_PREFIX.MediaLicense::TRAINED_ALGORITHMIC_MEDIA,
            $rights->digitalSourceType,
        );
    }

    public function testASuppliedArtistIsTrimmedOfItsTerminator(): void
    {
        // EXIF ASCII counts its NUL, and the browser forwards the value as it found it.
        $rights = $this->reader->readSupplied($this->supplied(['artist' => "Enrico Romanzi\0"]));

        self::assertSame(['Enrico Romanzi'], $rights->creator);
    }

    public function testABlankSuppliedValueIsNoClaim(): void
    {
        $rights = $this->reader->readSupplied($this->supplied(['artist' => "   \0", 'copyright' => '  ']));

        self::assertSame([], $rights->toCustomProperties());
    }

    public function testUnusableSuppliedInputIsIgnoredRatherThanTrusted(): void
    {
        foreach ([
            '',
            'not json',
            '"a string"',
            '{"xmp": 42}',
            '{"xmp": "not base64 @@@"}',
            // Past the cap the value is refused before it is decoded at all.
            json_encode(['xmp' => str_repeat('A', 9 * 1024 * 1024)], \JSON_THROW_ON_ERROR),
        ] as $json) {
            self::assertSame([], $this->reader->readSupplied($json)->toCustomProperties(), substr($json, 0, 40));
        }
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
