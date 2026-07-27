<?php

namespace Pushword\Core\Tests\Image\License;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Image\License\C2paManifest;
use Pushword\Core\Image\License\EmbeddedRightsReader;
use Pushword\Core\Image\License\MediaLicense;
use Symfony\Component\Filesystem\Filesystem;

final class C2paManifestTest extends TestCase
{
    private const string AI = MediaLicense::DIGITAL_SOURCE_TYPE_PREFIX.MediaLicense::TRAINED_ALGORITHMIC_MEDIA;

    private string $dir;

    private EmbeddedRightsReader $reader;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/pw-c2pa-'.bin2hex(random_bytes(6));
        new Filesystem()->mkdir($this->dir);
        $this->reader = new EmbeddedRightsReader();
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->dir);
    }

    public function testAnEmptyManifestYieldsNothing(): void
    {
        self::assertSame('', C2paManifest::read('')->digitalSourceType);
    }

    public function testTheActionsAssertionYieldsItsDigitalSourceType(): void
    {
        self::assertSame(
            self::AI,
            C2paManifest::read(ImageMetadataFixture::c2paActions(self::AI))->digitalSourceType,
        );
    }

    /** v1 and v2 of the label are both in the wild. */
    public function testBothActionLabelRevisionsAreRead(): void
    {
        foreach (['c2pa.actions', 'c2pa.actions.v2'] as $label) {
            self::assertSame(
                self::AI,
                C2paManifest::read(ImageMetadataFixture::c2paActions(self::AI, $label))->digitalSourceType,
                $label,
            );
        }
    }

    /**
     * A camera writes the same assertion to say the opposite. Reading the value rather
     * than the presence of the box is what keeps a photograph from being labelled AI.
     */
    public function testACapturedPhotographIsNotReportedAsGenerated(): void
    {
        $manifest = ImageMetadataFixture::c2paActions(MediaLicense::DIGITAL_SOURCE_TYPE_PREFIX.'digitalCapture');

        self::assertSame(
            MediaLicense::DIGITAL_SOURCE_TYPE_PREFIX.'digitalCapture',
            C2paManifest::read($manifest)->digitalSourceType,
        );
    }

    /**
     * Provenance is never authorship: the manifest also names the signer, and turning
     * that into a creator would publish a claim about ownership nobody made.
     */
    public function testNothingButProvenanceIsTakenFromTheManifest(): void
    {
        $rights = C2paManifest::read(ImageMetadataFixture::c2paActions(self::AI));

        self::assertSame([], $rights->creator);
        self::assertSame('', $rights->creditText);
        self::assertSame('', $rights->copyrightNotice);
        self::assertSame('', $rights->license);
        // digitalSourceType is provenance, not a rights claim, so it must not make the
        // file look third-party and block the site's own licensing.
        self::assertFalse($rights->hasRightsValue());
    }

    public function testGarbageIsNotAManifest(): void
    {
        self::assertSame('', C2paManifest::read(random_bytes(512))->digitalSourceType);
        self::assertSame('', C2paManifest::read('jumb')->digitalSourceType);
        self::assertSame('', C2paManifest::read(str_repeat("\x00", 64))->digitalSourceType);
    }

    /** A box claiming to be longer than the bytes it sits in must not be followed. */
    public function testAnOverlongBoxLengthIsRejected(): void
    {
        self::assertSame('', C2paManifest::read(pack('N', 0xFFFFFF).'jumbtruncated')->digitalSourceType);
    }

    // --- through the container, end to end ---

    public function testEachContainerCarriesTheManifestToTheReader(): void
    {
        $manifest = ImageMetadataFixture::c2paActions(self::AI);

        $files = [
            'png' => ImageMetadataFixture::writePng($this->dir.'/ai.png', c2pa: $manifest),
            'webp' => ImageMetadataFixture::writeWebp($this->dir.'/ai.webp', c2pa: $manifest),
            'jpeg' => ImageMetadataFixture::write($this->dir.'/ai.jpg', c2pa: $manifest),
        ];

        foreach ($files as $format => $path) {
            self::assertSame(self::AI, $this->reader->read($path)->digitalSourceType, $format);
        }
    }

    /**
     * A JPEG splits one manifest across APP11 segments once it outgrows 64 KB, so the
     * fragments have to be put back together in sequence order.
     */
    public function testAJpegManifestSplitAcrossSegmentsIsReassembled(): void
    {
        foreach ([2, 5, 11] as $fragments) {
            $path = ImageMetadataFixture::write(
                $this->dir.'/split-'.$fragments.'.jpg',
                c2pa: ImageMetadataFixture::c2paActions(self::AI),
                c2paFragments: $fragments,
            );

            self::assertSame(self::AI, $this->reader->read($path)->digitalSourceType, $fragments.' fragments');
        }
    }

    /**
     * The file this whole feature was checked against carries no XMP, no IPTC and no
     * EXIF — only C2PA. Before it was read, such a file reported nothing at all.
     */
    public function testAFileWhoseOnlyMetadataIsC2paIsStillRecognised(): void
    {
        $path = ImageMetadataFixture::writePng($this->dir.'/only-c2pa.png', c2pa: ImageMetadataFixture::c2paActions(self::AI));

        $properties = $this->reader->read($path)->toCustomProperties();

        self::assertSame([MediaLicense::DIGITAL_SOURCE_TYPE => self::AI], $properties);
    }

    /** XMP is what a human edits, so it outranks the generator's own note. */
    public function testXmpDigitalSourceTypeWinsOverTheManifest(): void
    {
        $path = ImageMetadataFixture::writePng(
            $this->dir.'/both.png',
            ImageMetadataFixture::packet(
                '<rdf:Description rdf:about="" xmlns:iptcExt="http://iptc.org/std/Iptc4xmpExt/2008-02-29/">'
                .'<iptcExt:DigitalSourceType>digitalCapture</iptcExt:DigitalSourceType></rdf:Description>',
            ),
            c2pa: ImageMetadataFixture::c2paActions(self::AI),
        );

        self::assertSame(
            MediaLicense::DIGITAL_SOURCE_TYPE_PREFIX.'digitalCapture',
            $this->reader->read($path)->digitalSourceType,
        );
    }
}
