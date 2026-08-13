<?php

namespace Pushword\Core\Tests\Twig;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

#[Group('integration')]
final class ImageTemplateTest extends KernelTestCase
{
    private function getTwig(): Environment
    {
        self::bootKernel();

        /** @var Environment */
        return self::getContainer()->get('twig');
    }

    private function createMedia(int $width = 1200, int $height = 800): Media
    {
        $media = new Media();
        $media->setFileName('test-image.jpg');
        $media->setAlt('Test image');
        $media->imageData->setDimensions([$width, $height]);

        return $media;
    }

    public function testResponsiveModeGeneratesMultipleSrcset(): void
    {
        $twig = $this->getTwig();
        $html = $twig->render('@PushwordCore/component/image.html.twig', [
            'image' => $this->createMedia(),
        ]);

        // Responsive mode (default) should generate multiple breakpoints
        self::assertStringContainsString('<picture', $html);
        self::assertStringContainsString('576w', $html);
        self::assertStringContainsString('768w', $html);
        self::assertStringContainsString('992w', $html);
        self::assertStringContainsString('width="1200"', $html);
        self::assertStringContainsString('height="800"', $html);
    }

    public function testImgFallbackServesOriginalFormatNotWebp(): void
    {
        $twig = $this->getTwig();
        $html = $twig->render('@PushwordCore/component/image.html.twig', [
            'image' => $this->createMedia(),
        ]);

        // The modern <source> advertises webp...
        self::assertStringContainsString('type="image/webp"', $html);

        // ...but the <img> fallback must point at the original (.jpg) source, so a
        // browser without webp support still has a usable image. Isolate the <img>.
        $img = substr($html, (int) strpos($html, '<img'));
        self::assertStringContainsString('test-image.jpg', $img);
        self::assertStringNotContainsString('.webp', $img);
    }

    public function testSingleFilterModeUsesActualDimensions(): void
    {
        $twig = $this->getTwig();
        $html = $twig->render('@PushwordCore/component/image.html.twig', [
            'image' => $this->createMedia(900, 600),
            'mode' => 'xs',
            'page' => new Page(),
        ]);

        // Should use actual image dimensions, not hardcoded 1000x1000
        self::assertStringContainsString('width="900"', $html);
        self::assertStringContainsString('height="600"', $html);
        self::assertStringNotContainsString('width="1000"', $html);
    }

    public function testNoThumbModeHardcodedDimensions(): void
    {
        $twig = $this->getTwig();

        // Even if someone passes mode='thumb' (with custom config), dimensions should come from the image
        $html = $twig->render('@PushwordCore/component/image.html.twig', [
            'image' => $this->createMedia(500, 400),
            'mode' => 'md',
            'page' => new Page(),
        ]);

        self::assertStringContainsString('width="500"', $html);
        self::assertStringContainsString('height="400"', $html);
        self::assertStringNotContainsString('1000', $html);
    }

    /**
     * Every other test here renders the component directly, handing it a `sizes`
     * variable — which passes even when `image()` itself has no parameter to carry
     * one. A site upgrading found out the hard way: `Unknown argument "sizes" for
     * function "image(...)"`, a 500 on every page with an image. This pins the
     * argument name callers spell, and the wiring down to the attribute.
     */
    public function testImageFunctionTakesSizesAsANamedArgument(): void
    {
        $twig = $this->getTwig();

        $html = $twig->createTemplate("{{ image(media, sizes: '(max-width: 767px) 100vw, 700px') }}")
            ->render(['media' => $this->createMedia()]);

        self::assertStringContainsString('sizes="(max-width: 767px) 100vw, 700px"', $html);
    }

    /**
     * The whole point of `sizes`: every browser that understands webp takes the
     * <source> and never looks at the <img>, so a `sizes` reaching only the <img>
     * is dead on arrival — which is what a caller passing it through image_attr used
     * to get.
     */
    public function testSizesReachesTheModernSource(): void
    {
        $twig = $this->getTwig();
        $html = $twig->render('@PushwordCore/component/image.html.twig', [
            'image' => $this->createMedia(),
            'sizes' => '(max-width: 1023px) 100vw, 773px',
        ]);

        $source = substr($html, (int) strpos($html, '<source'), (int) strpos($html, '<img') - (int) strpos($html, '<source'));
        self::assertStringContainsString('sizes="(max-width: 1023px) 100vw, 773px"', $source);
    }

    /**
     * mergeAttr() concatenates scalars ("a" + "b" => "a b"), so a `sizes` handed over
     * in image_attr came out welded to the default ladder — a malformed attribute.
     * It is now read as the parameter and emitted exactly once.
     */
    public function testSizesGivenThroughImageAttrIsHonouredOnceAndNotConcatenated(): void
    {
        $twig = $this->getTwig();
        $html = $twig->render('@PushwordCore/component/image.html.twig', [
            'image' => $this->createMedia(),
            'image_attr' => ['sizes' => '50vw'],
        ]);

        self::assertSame(1, substr_count($html, 'sizes='), 'sizes belongs on the <source>, once.');
        self::assertStringContainsString('sizes="50vw"', $html);
        self::assertStringNotContainsString('100vw', $html);
    }

    /**
     * The default ladder announced the width of the breakpoint, not of the viewport:
     * "(max-width: 576px) 576px" told a 412 px phone the image was 576 px wide, so at
     * DPR 1.75 it asked for 1008 px and downloaded the 1200 w candidate for a thumbnail.
     */
    public function testDefaultSizesIsTheViewportNotTheBreakpointLadder(): void
    {
        $twig = $this->getTwig();
        $html = $twig->render('@PushwordCore/component/image.html.twig', [
            'image' => $this->createMedia(),
        ]);

        self::assertStringContainsString('sizes="100vw"', $html);
        self::assertStringNotContainsString('(max-width: 576px) 576px', $html);
    }

    /**
     * A single-filter srcset used to label every filter `576w`, whatever it downscales
     * to, so a browser reading it was lied to about every filter but xs.
     */
    public function testSingleFilterModeLabelsTheSrcsetWithTheFilterTargetWidth(): void
    {
        $twig = $this->getTwig();
        $html = $twig->render('@PushwordCore/component/image.html.twig', [
            'image' => $this->createMedia(),
            'mode' => 'md',
            'page' => new Page(),
        ]);

        self::assertStringContainsString('/media/md/test-image.webp 992w', $html);
        self::assertStringNotContainsString('576w', $html);
    }

    /**
     * height_300 caps the height, so no `w` descriptor is truthful. A lone entry without
     * one is a valid srcset — and `sizes` has nothing to steer, so it is left out.
     */
    public function testHeightCappedFilterGetsNoWidthDescriptorAndNoSizes(): void
    {
        $twig = $this->getTwig();
        $html = $twig->render('@PushwordCore/component/image.html.twig', [
            'image' => $this->createMedia(),
            'mode' => 'height_300',
            'page' => new Page(),
        ]);

        self::assertStringContainsString('srcset="/media/height_300/test-image.webp"', $html);
        self::assertStringNotContainsString('sizes=', $html);
    }

    /** One candidate is one choice: `sizes` would steer nothing and is left out. */
    public function testSingleFilterModeEmitsNoSizes(): void
    {
        $twig = $this->getTwig();
        $html = $twig->render('@PushwordCore/component/image.html.twig', [
            'image' => $this->createMedia(),
            'mode' => 'xs',
            'sizes' => '50vw',
            'page' => new Page(),
        ]);

        self::assertStringNotContainsString('sizes=', $html);
    }

    /**
     * The <img> behind a webp <source> is only reached by a browser that supports none
     * of the offered formats. The ladder does not exist in the source format (xs/sm/lg/xl
     * are webp-only), so it must not advertise one. It used to be emitted and swallowed —
     * mergeAttr() dropped the macro's Twig\Markup as a non-scalar — but since
     * piedweb/render-html-attributes v0.1.947 stringifies Stringable, the same markup
     * would now ship a srcset of URLs that 404.
     */
    public function testImgFallbackAdvertisesNoSrcsetBehindAModernSource(): void
    {
        $twig = $this->getTwig();
        $html = $twig->render('@PushwordCore/component/image.html.twig', [
            'image' => $this->createMedia(),
        ]);

        $img = substr($html, (int) strpos($html, '<img'));
        self::assertStringNotContainsString('srcset', $img);
        self::assertStringContainsString('src="/media/default/test-image.jpg"', $img);
    }

    public function testAltStripsMarkupComingFromATitle(): void
    {
        $twig = $this->getTwig();

        // Cards hand over a page h1, which carries markup. Rendered as-is it would
        // escape into the attribute and read as literal tags to a screen reader.
        $html = $twig->render('@PushwordCore/component/image.html.twig', [
            'image' => $this->createMedia(),
            'image_alt' => 'Yann Gouffault<br><small>Mountain leader</small>',
        ]);

        self::assertStringContainsString('alt="Yann Gouffault Mountain leader"', $html);
        self::assertStringNotContainsString('&lt;', $html);
    }

    public function testAltFallsBackToTheMediaAltWhenNoneIsGiven(): void
    {
        $twig = $this->getTwig();
        $html = $twig->render('@PushwordCore/component/image.html.twig', [
            'image' => $this->createMedia(),
        ]);

        self::assertStringContainsString('alt="Test image"', $html);
    }
}
