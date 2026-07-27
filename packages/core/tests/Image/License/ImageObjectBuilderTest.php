<?php

namespace Pushword\Core\Tests\Image\License;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Media;
use Pushword\Core\Image\License\ImageObjectBuilder;
use Pushword\Core\Image\License\MediaLicense;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class ImageObjectBuilderTest extends KernelTestCase
{
    private ImageObjectBuilder $builder;

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var ImageObjectBuilder $builder */
        $builder = self::getContainer()->get(ImageObjectBuilder::class);
        $this->builder = $builder;
    }

    /** @param array<string, mixed> $license */
    private function media(array $license = [], string $fileName = 'photo.jpg'): Media
    {
        $media = new Media();
        $media->setFileName($fileName);
        $media->setMimeType('image/jpeg');
        $media->setAlt('Refuge du Gioberney');
        $media->setDimensions([1200, 800]);

        foreach ($license as $key => $value) {
            $media->setCustomProperty($key, $value);
        }

        return $media;
    }

    public function testAMediaWithoutLicensePropertiesEmitsNothing(): void
    {
        self::assertSame([], $this->builder->build($this->media()));
        self::assertSame('', $this->builder->render($this->media()));
    }

    public function testACreatorIsEnough(): void
    {
        $imageObject = $this->builder->build($this->media([
            MediaLicense::CREATOR => [['name' => 'Enrico Romanzi', 'type' => 'Person']],
        ]));

        self::assertSame('ImageObject', $imageObject['@type']);
        self::assertSame(['@type' => 'Person', 'name' => 'Enrico Romanzi'], $imageObject['creator']);
        self::assertSame('Refuge du Gioberney', $imageObject['name']);
        self::assertSame(1200, $imageObject['width']);
    }

    /** Google's list of qualifying properties does not include acquireLicensePage. */
    public function testAcquireLicensePageAloneDoesNotQualify(): void
    {
        self::assertSame([], $this->builder->build($this->media([
            MediaLicense::ACQUIRE_LICENSE_PAGE => 'https://example.tld/buy',
        ])));
    }

    public function testAcquireLicensePageRidesAlongWithAQualifyingProperty(): void
    {
        $imageObject = $this->builder->build($this->media([
            MediaLicense::LICENSE => 'https://example.tld/terms',
            MediaLicense::ACQUIRE_LICENSE_PAGE => 'https://example.tld/buy',
        ]));

        self::assertSame('https://example.tld/buy', $imageObject['acquireLicensePage']);
    }

    /**
     * The values are file-supplied, so an uploaded image's XMP is an injection vector.
     * JSON_HEX_TAG is what keeps a creator name from closing the script block.
     */
    public function testAScriptTagInAValueCannotCloseTheBlock(): void
    {
        $html = $this->builder->render($this->media([
            MediaLicense::CREATOR => ['</script><script>alert(1)</script>'],
        ]));

        self::assertStringNotContainsString('</script><script>', $html);
        self::assertSame(1, substr_count($html, '</script>'));
        self::assertStringContainsString('<', $html);
    }

    public function testQuotesAndAccentsStayValidJson(): void
    {
        $html = $this->builder->render($this->media([
            MediaLicense::CREATOR => ["Enrico Romanzi's", 'Élie-Eynaud'],
        ]));

        $json = (string) preg_replace('#^<script[^>]*>|</script>$#', '', $html);
        $decoded = json_decode($json, true);

        self::assertIsArray($decoded);
        self::assertStringContainsString('Élie-Eynaud', $json);
    }

    public function testASingleCreatorIsABareObjectAndTwoAreAnArray(): void
    {
        $one = $this->arrayValue($this->builder->build($this->media([MediaLicense::CREATOR => ['Solo']])), 'creator');
        self::assertArrayHasKey('@type', $one);

        $two = $this->arrayValue($this->builder->build($this->media([
            MediaLicense::CREATOR => ['Dominique VIVARES', 'Jean Dupont'],
        ])), 'creator');

        self::assertCount(2, $two);
        self::assertSame('Dominique VIVARES', $this->arrayValue($two, 0)['name']);
    }

    /**
     * The whole point of storing {name, type} per creator: one image credited to a
     * photographer and to the agency that commissioned it emits two different types.
     */
    public function testEachCreatorEmitsItsOwnType(): void
    {
        $creators = $this->arrayValue($this->builder->build($this->media([
            MediaLicense::CREATOR => [
                ['name' => 'Enrico Romanzi', 'type' => 'Person'],
                ['name' => 'Altimood', 'type' => 'Organization'],
            ],
        ])), 'creator');

        self::assertSame(['@type' => 'Person', 'name' => 'Enrico Romanzi'], $this->arrayValue($creators, 0));
        self::assertSame(['@type' => 'Organization', 'name' => 'Altimood'], $this->arrayValue($creators, 1));
    }

    public function testAnEmptyCreatorTypeFallsBackRatherThanEmittingAnEmptyType(): void
    {
        $imageObject = $this->builder->build($this->media([MediaLicense::CREATOR => [['name' => 'Anonyme', 'type' => '']]]));

        self::assertSame('Person', $this->arrayValue($imageObject, 'creator')['@type']);
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array<array-key, mixed>
     */
    private function arrayValue(array $values, string|int $key): array
    {
        $value = $values[$key] ?? null;
        self::assertIsArray($value);

        return $value;
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private function stringValue(array $values, string $key): string
    {
        $value = $values[$key] ?? null;
        self::assertIsString($value);

        return $value;
    }

    /** No schema.org property on ImageObject carries it, so it must never be invented. */
    public function testDigitalSourceTypeIsNeverEmitted(): void
    {
        $imageObject = $this->builder->build($this->media([
            MediaLicense::CREDIT_TEXT => 'Altimood',
            MediaLicense::DIGITAL_SOURCE_TYPE => MediaLicense::DIGITAL_SOURCE_TYPE_PREFIX.'trainedAlgorithmicMedia',
        ]));

        self::assertArrayNotHasKey('digitalSourceType', $imageObject);
        self::assertStringNotContainsString('digitalsourcetype', strtolower(json_encode($imageObject, \JSON_THROW_ON_ERROR)));
    }

    public function testContentUrlIsAbsolute(): void
    {
        $imageObject = $this->builder->build($this->media([MediaLicense::CREDIT_TEXT => 'Altimood']));

        $contentUrl = $this->stringValue($imageObject, 'contentUrl');
        self::assertMatchesRegularExpression('#^https?://[^/]+/#', $contentUrl);
        self::assertStringContainsString('photo', $contentUrl);
    }

    /**
     * Google associates the ImageObject with the image it crawled on the page. The
     * default filter's webp variant appears nowhere in the markup — the <source
     * srcset> lists the breakpoint filters — so contentUrl has to be the <img src>,
     * which serves the source format.
     */
    public function testContentUrlIsTheDefaultFilterInTheSourceFormat(): void
    {
        $contentUrl = $this->stringValue(
            $this->builder->build($this->media([MediaLicense::CREDIT_TEXT => 'Altimood'])),
            'contentUrl',
        );

        self::assertStringEndsWith('/default/photo.jpg', $contentUrl);
    }

    /** A webp source has no separate original: both the src and contentUrl are the webp. */
    public function testAWebpSourceKeepsItsOwnExtension(): void
    {
        $media = $this->media([MediaLicense::CREDIT_TEXT => 'Altimood'], 'photo.webp');
        $media->setMimeType('image/webp');

        self::assertStringEndsWith(
            '/default/photo.webp',
            $this->stringValue($this->builder->build($media), 'contentUrl'),
        );
    }

    public function testANonImageMediaNeverEmits(): void
    {
        $media = $this->media([MediaLicense::CREDIT_TEXT => 'Altimood'], 'doc.pdf');
        $media->setMimeType('application/pdf');

        self::assertSame([], $this->builder->build($media));
    }

    /**
     * image.html.twig returns early for an SVG, and Media::isImage() already excludes
     * it everywhere else in the codebase (no dimensions, no cache variants). Pinned so
     * the two branches of the template cannot drift apart.
     */
    public function testAnSvgDoesNotEmit(): void
    {
        $media = $this->media([MediaLicense::CREDIT_TEXT => 'Altimood'], 'logo.svg');
        $media->setMimeType('image/svg+xml');

        self::assertFalse($media->isImage());
        self::assertSame('', $this->builder->render($media));
    }
}
