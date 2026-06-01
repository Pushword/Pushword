<?php

namespace Pushword\AdminBlockEditor\Tests;

use PHPUnit\Framework\TestCase;
use Pushword\AdminBlockEditor\Service\MediaCaptionRenamer;

final class MediaCaptionExtractionTest extends TestCase
{
    public function testExtractsImageCaptions(): void
    {
        $content = "![Hikers Zugspitze Austria](/media/md/image-2.jpg)\n\nSome text.";

        self::assertSame(
            [['/media/md/image-2.jpg', 'Hikers Zugspitze Austria']],
            MediaCaptionRenamer::extractCaptionedMedia($content),
        );
    }

    public function testExtractsImageInsideLink(): void
    {
        // linkTune wraps the image: [![caption](img)](link)
        $content = '[![Mountain View](/media/md/photo.jpg)](https://example.com)';

        self::assertSame(
            [['/media/md/photo.jpg', 'Mountain View']],
            MediaCaptionRenamer::extractCaptionedMedia($content),
        );
    }

    public function testIgnoresImageWithMarkdownTitle(): void
    {
        $content = '![Caption](/media/md/photo.jpg "a title")';

        self::assertSame(
            [['/media/md/photo.jpg', 'Caption']],
            MediaCaptionRenamer::extractCaptionedMedia($content),
        );
    }

    public function testExtractsGalleryCaptions(): void
    {
        $content = '{{ gallery({"photo.jpg":"Lake Sunset","other.jpg":"Forest Trail"}, clickable: true) }}';

        self::assertSame(
            [
                ['photo.jpg', 'Lake Sunset'],
                ['other.jpg', 'Forest Trail'],
            ],
            MediaCaptionRenamer::extractCaptionedMedia($content),
        );
    }

    public function testExtractsGalleryWithImagesNamedArgument(): void
    {
        $content = '{{ gallery(images: {"photo.jpg":"Lake Sunset"}) }}';

        self::assertSame(
            [['photo.jpg', 'Lake Sunset']],
            MediaCaptionRenamer::extractCaptionedMedia($content),
        );
    }

    public function testCombinesImageAndGallery(): void
    {
        $content = "![Cover Photo](/media/md/cover.jpg)\n\n{{ gallery({\"a.jpg\":\"First\"}) }}";

        self::assertSame(
            [
                ['/media/md/cover.jpg', 'Cover Photo'],
                ['a.jpg', 'First'],
            ],
            MediaCaptionRenamer::extractCaptionedMedia($content),
        );
    }

    public function testReturnsEmptyForContentWithoutMedia(): void
    {
        self::assertSame([], MediaCaptionRenamer::extractCaptionedMedia('Just a paragraph.'));
    }

    public function testSkipsMalformedGalleryJson(): void
    {
        $content = '{{ gallery({not valid json}) }}';

        self::assertSame([], MediaCaptionRenamer::extractCaptionedMedia($content));
    }
}
