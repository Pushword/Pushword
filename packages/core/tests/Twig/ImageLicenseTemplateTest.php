<?php

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
            MediaLicense::CREDIT_TEXT => 'Altimood',
            MediaLicense::LICENSE => 'https://altimood.test/mentions-legales',
            MediaLicense::ACQUIRE_LICENSE_PAGE => 'https://altimood.test/contact',
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
        $html = $this->renderImage([MediaLicense::CREDIT_TEXT => 'Altimood']);

        self::assertSame(1, preg_match('#<img [^>]*\bsrc="([^"]+)"#', $html, $src));
        self::assertSame(1, preg_match('#"contentUrl":"([^"]+)"#', $html, $contentUrl));

        self::assertStringEndsWith($src[1], $contentUrl[1]);
    }

    /** Google asks for one node per rendered instance, not one per page. */
    public function testTheSameImageTwiceOnAPageEmitsTwoNodes(): void
    {
        $license = [MediaLicense::CREDIT_TEXT => 'Altimood'];
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
            $this->media([MediaLicense::CREDIT_TEXT => 'Altimood']),
            link: 'https://altimood.test/page',
            obfuscate: false,
        );

        self::assertStringContainsString('application/ld+json', $html);
        self::assertStringContainsString('<a ', $html);
    }

    /** The template returns early for an SVG; Media::isImage() excludes it too. */
    public function testAnSvgRendersWithoutJsonLd(): void
    {
        $html = $this->renderImage([MediaLicense::CREDIT_TEXT => 'Altimood'], 'logo.svg');

        self::assertStringContainsString('<picture', $html);
        self::assertStringNotContainsString('application/ld+json', $html);
    }
}
