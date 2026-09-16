<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Twig;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Media;
use Pushword\Core\Image\License\MediaLicense;
use Pushword\Core\Twig\MediaExtension;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

/**
 * The JSON-LD is emitted by component/image.html.twig itself, so every caller gets
 * it: image_inline is a shim over image(), and images_gallery calls image() per item.
 */
#[Group('integration')]
final class ImageLicenseTemplateTest extends KernelTestCase
{
    /** @param array<string, mixed> $license */
    private function media(array $license = [], string $fileName = 'test-image.jpg'): Media
    {
        $media = new Media();
        $media->setFileName($fileName);
        $media->setMimeType(str_ends_with($fileName, '.svg') ? 'image/svg+xml' : 'image/jpeg');
        $media->setAlt('Refuge du Gioberney');
        $media->imageData->setDimensions([1200, 800]);

        foreach ($license as $key => $value) {
            $media->setCustomProperty($key, $value);
        }

        return $media;
    }

    private function twig(): Environment
    {
        self::bootKernel();

        /** @var Environment */
        return self::getContainer()->get('twig');
    }

    /** @param array<string, mixed> $license */
    private function renderImage(array $license, string $fileName = 'test-image.jpg'): string
    {
        return $this->twig()->render('@PushwordCore/component/image.html.twig', [
            'image' => $this->media($license, $fileName),
        ]);
    }

    public function testAMediaWithoutLicenseEmitsNoScriptAtAll(): void
    {
        $html = $this->renderImage([]);

        self::assertStringContainsString('<picture', $html);
        self::assertStringNotContainsString('application/ld+json', $html);
    }

    public function testALicensedMediaEmitsAnImageObjectNextToItsPicture(): void
    {
        $html = $this->renderImage([
            MediaLicense::CREDIT_TEXT => 'ExampleCreditText',
            MediaLicense::LICENSE => 'https://example.test/mentions-legales',
            MediaLicense::ACQUIRE_LICENSE_PAGE => 'https://example.test/contact',
        ]);

        self::assertStringContainsString('application/ld+json', $html);
        self::assertStringContainsString('"@type":"ImageObject"', $html);
        self::assertStringContainsString('acquireLicensePage', $html);
        self::assertLessThan(strpos($html, 'ld+json'), (int) strpos($html, '<picture'));
    }

    /**
     * The whole point of the node is to describe the image Google crawled on this
     * page. contentUrl and the <img src> are built by two different pieces of code
     * (ImageObjectBuilder and this template) — pinned here so they cannot drift.
     */
    public function testContentUrlIsExactlyTheImgSrc(): void
    {
        $html = $this->renderImage([MediaLicense::CREDIT_TEXT => 'ExampleCreditText']);

        self::assertSame(1, preg_match('#<img [^>]*\bsrc="([^"]+)"#', $html, $src));
        self::assertSame(1, preg_match('#"contentUrl":"([^"]+)"#', $html, $contentUrl));

        self::assertStringEndsWith($src[1], $contentUrl[1]);
    }

    /** Google asks for one node per rendered instance, not one per page. */
    public function testTheSameImageTwiceOnAPageEmitsTwoNodes(): void
    {
        $license = [MediaLicense::CREDIT_TEXT => 'ExampleCreditText'];
        $html = $this->renderImage($license).$this->renderImage($license);

        self::assertSame(2, substr_count($html, 'application/ld+json'));
    }

    /** The gallery captures image() into a variable and may wrap it in a link. */
    public function testTheJsonLdSurvivesBeingWrappedInALink(): void
    {
        self::bootKernel();

        /** @var MediaExtension $mediaExtension */
        $mediaExtension = self::getContainer()->get(MediaExtension::class);

        $html = $mediaExtension->renderImage(
            $this->media([MediaLicense::CREDIT_TEXT => 'ExampleCreditText']),
            link: 'https://example.test/page',
            obfuscate: false,
        );

        self::assertStringContainsString('application/ld+json', $html);
        self::assertStringContainsString('<a ', $html);
    }

    /** The template returns early for an SVG; Media::isImage() excludes it too. */
    public function testAnSvgRendersWithoutJsonLd(): void
    {
        $html = $this->renderImage([MediaLicense::CREDIT_TEXT => 'ExampleCreditText'], 'logo.svg');

        self::assertStringContainsString('<picture', $html);
        self::assertStringNotContainsString('application/ld+json', $html);
    }

    /**
     * The credit has to reach the markup as well as the JSON-LD, or it is published
     * for machines and hidden from the people the licence asks us to inform.
     */
    public function testACreditedMediaCarriesItsAttributionInTheTitle(): void
    {
        $html = $this->renderImage([
            MediaLicense::CREDIT_TEXT => 'Zde / Wikimedia',
            MediaLicense::LICENSE => 'https://creativecommons.org/licenses/by-sa/4.0/',
        ]);

        self::assertStringContainsString('title="© Zde / Wikimedia (CC BY-SA 4.0)"', $html);
    }

    /**
     * The whole reason the credit moved out of the alt: the alt is the description a
     * screen reader reads in place of the image, and a photographer's name is not
     * part of what the photo shows.
     */
    public function testTheAltStaysADescriptionAndNeverGainsTheCredit(): void
    {
        $html = $this->renderImage([MediaLicense::CREDIT_TEXT => 'Wilfrid Valette']);

        self::assertSame(1, preg_match('#<img [^>]*\balt="([^"]*)"#', $html, $alt));
        self::assertSame('Refuge du Gioberney', $alt[1]);
    }

    public function testAMediaWithoutACreditGetsNoTitleAtAll(): void
    {
        self::assertStringNotContainsString('title=', $this->renderImage([]));
    }

    /**
     * mergeAttr() concatenates scalars instead of overriding them, so a caller's title
     * and ours would come out welded into one malformed attribute. The caller wins,
     * and nothing is appended to it.
     */
    public function testACallerTitleIsLeftAloneRatherThanConcatenated(): void
    {
        $html = $this->twig()->render('@PushwordCore/component/image.html.twig', [
            'image' => $this->media([MediaLicense::CREDIT_TEXT => 'Wilfrid Valette']),
            'image_attr' => ['title' => 'Le refuge au petit matin'],
        ]);

        self::assertSame(1, preg_match('#<img [^>]*\\btitle="([^"]*)"#', $html, $title));
        self::assertSame('Le refuge au petit matin', $title[1]);

        // The JSON-LD still carries the creditText — the caller overrode the tooltip,
        // not the media's own claim.
        self::assertStringContainsString('"creditText":"Wilfrid Valette"', $html);
    }

    /** The SVG branch returns before the srcset work and used to miss every addition. */
    public function testAnSvgCarriesItsCreditToo(): void
    {
        $html = $this->renderImage([MediaLicense::CREDIT_TEXT => 'ExampleCreator'], 'logo.svg');

        self::assertStringContainsString('title="© ExampleCreator"', $html);
    }

    /**
     * A body image written as `![alt](photo.jpg)` renders through the same component,
     * with the markdown's own alt passed in. The credit must still ride along — that
     * path is how the bulk of an imported library is displayed.
     */
    public function testABodyImageWithItsOwnAltStillCarriesTheCredit(): void
    {
        self::bootKernel();

        /** @var MediaExtension $mediaExtension */
        $mediaExtension = self::getContainer()->get(MediaExtension::class);

        $html = $mediaExtension->renderImage(
            $this->media([MediaLicense::CREDIT_TEXT => 'Thomas Praire']),
            alt: 'Descente vers le lac',
        );

        self::assertStringContainsString('alt="Descente vers le lac"', $html);
        self::assertStringContainsString('title="© Thomas Praire"', $html);
    }
}
