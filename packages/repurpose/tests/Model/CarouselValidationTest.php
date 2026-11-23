<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Tests\Model;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Repurpose\Service\CarouselFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Group('integration')]
final class CarouselValidationTest extends KernelTestCase
{
    /**
     * @param array<string, mixed> $spec
     *
     * @return array<string, string> propertyPath => message
     */
    private function violations(array $spec): array
    {
        self::bootKernel();
        $validator = self::getContainer()->get(ValidatorInterface::class);
        $carousel = new CarouselFactory()->fromArray($spec);

        $out = [];
        foreach ($validator->validate($carousel) as $violation) {
            $out[$violation->getPropertyPath()] = (string) $violation->getMessage();
        }

        return $out;
    }

    public function testMinimalValidSpecPasses(): void
    {
        $violations = $this->violations([
            'page' => 'blog/mon-article',
            'network' => 'linkedin',
            'format' => 'linkedin-4-5',
            'slides' => [['title' => 'Hello', 'image' => ['media' => '1.jpg']]],
        ]);

        self::assertSame([], $violations);
    }

    public function testAudioRejectsUnsafeFilesAndOutOfRangeMixSettings(): void
    {
        $spec = ['page' => 'x', 'network' => 'instagram', 'format' => 'story-9-16', 'slides' => [['title' => 'Hi']]];
        foreach (['voice.mp3', 'track.WAV', 'music.m4a', 'voice.ogg', 'music.flac'] as $media) {
            self::assertSame([], $this->violations([...$spec, 'audio' => ['voice' => ['media' => $media, 'volume' => 0, 'fadeOut' => 30]]]));
        }

        foreach (['', '../voice.mp3', 'https://example.test/a.wav', 'photo.jpg', "a.mp3\n"] as $media) {
            self::assertArrayHasKey('audio.voice.media', $this->violations([...$spec, 'audio' => ['voice' => ['media' => $media]]]));
        }

        $violations = $this->violations([...$spec, 'audio' => [
            'voice' => ['media' => 'voice.wav', 'volume' => -1, 'fadeIn' => -0.1],
            'music' => ['media' => 'music.wav', 'volume' => 1.1, 'fadeOut' => 31],
        ]]);
        foreach (['audio.voice.volume', 'audio.voice.fadeIn', 'audio.music.volume', 'audio.music.fadeOut'] as $path) {
            self::assertArrayHasKey($path, $violations);
        }
    }

    public function testUnknownNetworkIsRejected(): void
    {
        $violations = $this->violations([
            'page' => 'x', 'network' => 'myspace', 'format' => 'linkedin-4-5',
            'slides' => [['title' => 'Hi']],
        ]);

        self::assertArrayHasKey('network', $violations);
    }

    public function testVideoAcceptsLocalMp4AndWebmFilenamesOnly(): void
    {
        $spec = ['page' => 'x', 'network' => 'instagram', 'format' => 'story-9-16', 'slides' => [['title' => 'Hi']]];
        foreach ([null, '', 'reel.mp4', 'ete-2026.WEBM'] as $video) {
            self::assertArrayNotHasKey('video', $this->violations([...$spec, 'video' => $video]));
        }

        foreach (['../reel.mp4', 'https://example.com/reel.mp4', 'reel.svg', 'reel.mp4?x=1', "reel.mp4\n", 'été.mp4'] as $video) {
            self::assertArrayHasKey('video', $this->violations([...$spec, 'video' => $video]), $video);
        }
    }

    public function testFormatMustBeAllowedForNetwork(): void
    {
        $violations = $this->violations([
            'page' => 'x', 'network' => 'instagram', 'format' => 'linkedin-4-5',
            'slides' => [['title' => 'Hi']],
        ]);

        self::assertArrayHasKey('format', $violations);
        self::assertStringContainsString('not allowed', $violations['format']);
    }

    public function testOverlayWithoutImageIsRejected(): void
    {
        $violations = $this->violations([
            'page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5',
            'slides' => [['title' => 'Hi', 'overlay' => 0.5]],
        ]);

        self::assertArrayHasKey('slides[0].overlay', $violations);
    }

    public function testFreeImageFramesFitInsideTheSlide(): void
    {
        $spec = ['page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5'];
        $image = ['media' => '1.jpg', 'x' => 0.4, 'y' => 0.3, 'width' => 0.6, 'height' => 0.7];
        self::assertSame([], $this->violations([...$spec, 'slides' => [['imageLayout' => 'free', 'images' => [$image]]]]));

        $violations = $this->violations([...$spec, 'slides' => [[
            'imageLayout' => 'free',
            'images' => [
                [...$image, 'width' => 0.61, 'height' => 0.71],
                ['media' => '1.jpg', 'x' => -0.2, 'y' => -0.2, 'width' => 1.1, 'height' => 1.1],
                ['media' => '1.jpg', 'x' => 1.1, 'y' => 1.1, 'width' => 0.04, 'height' => 0.04],
            ],
        ]]]);
        foreach ([['width', 'height'], ['x', 'y', 'width', 'height'], ['x', 'y', 'width', 'height']] as $index => $fields) {
            foreach ($fields as $field) {
                self::assertArrayHasKey('slides[0].images['.$index.'].'.$field, $violations);
            }
        }
    }

    public function testFreeTextAloneMakesASlideNonEmpty(): void
    {
        $violations = $this->violations([
            'page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5',
            'slides' => [['texts' => [['content' => 'Just a note']]]],
        ]);

        self::assertSame([], $violations);
    }

    public function testFreeTextBoxMustStayInsideTheFrame(): void
    {
        $violations = $this->violations([
            'page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5',
            'slides' => [['title' => 'Hi', 'texts' => [
                ['content' => 'Off frame', 'x' => 0.5, 'width' => 0.6],
                ['content' => 'Too big', 'size' => 0.5],
            ]]],
        ]);

        self::assertArrayHasKey('slides[0].texts[0].width', $violations);
        self::assertArrayHasKey('slides[0].texts[1].size', $violations);
    }

    public function testHighlightMustBeAHexColour(): void
    {
        $violations = $this->violations([
            'page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5',
            'slides' => [['title' => 'Hi', 'highlight' => 'yellow', 'texts' => [['content' => 'N', 'highlight' => 'red']]]],
        ]);

        self::assertArrayHasKey('slides[0].highlight', $violations);
        self::assertArrayHasKey('slides[0].texts[0].highlight', $violations);
    }

    public function testEmptySlideIsRejected(): void
    {
        $violations = $this->violations([
            'page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5',
            'slides' => [[]],
        ]);

        self::assertArrayHasKey('slides[0].title', $violations);
    }

    public function testCropBoundsAreValidatedForLegacyImages(): void
    {
        $spec = ['page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5'];
        $valid = ['media' => '1.jpg', 'focusX' => 0, 'focusY' => 1, 'zoom' => 5];
        self::assertSame([], $this->violations([...$spec, 'slides' => [['image' => $valid]]]));

        $invalidCrops = [
            ['focusX' => -0.1, 'focusY' => 1.1, 'zoom' => 0.9],
            ['focusX' => 1.1, 'focusY' => -0.1, 'zoom' => 5.1],
        ];
        foreach ($invalidCrops as $crop) {
            $violations = $this->violations([...$spec, 'slides' => [['image' => ['media' => '1.jpg', ...$crop]]]]);
            self::assertSame(['slides[0].images[0].focusX', 'slides[0].images[0].focusY', 'slides[0].images[0].zoom'], array_keys($violations));
        }
    }

    /**
     * The renderer paints anything it cannot embed as a flat grey placeholder, so
     * a typo, a video or an SVG must fail validation instead of exporting grey.
     */
    public function testSlideImageMustBeARasterImageOfTheMediaLibrary(): void
    {
        foreach (['typo.jpg', 'repurpose-nord.mp4', 'logo.svg'] as $media) {
            $violations = $this->violations([
                'page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5',
                'slides' => [['title' => 'Hi', 'images' => [['media' => '1.jpg'], ['media' => $media]], 'imageLayout' => 'split-v']],
            ]);

            self::assertSame(['slides[0].images[1].media' => 'No image named "'.$media.'" in the media library (JPEG, PNG, GIF or WebP).'], $violations);
        }
    }

    public function testCustomFontPairingOfInstalledFamiliesPasses(): void
    {
        self::assertSame([], $this->violations([
            'page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5',
            'fontPairing' => ['heading' => 'Poppins', 'body' => 'Inter'],
            'slides' => [['title' => 'Hi']],
        ]));
    }

    /**
     * The renderer would silently fall back to Roboto; validation names the
     * family and the command that installs it instead.
     */
    public function testAFontNotInstalledIsRejectedWithItsInstallCommand(): void
    {
        $spec = ['page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5', 'slides' => [['title' => 'Hi']]];

        self::assertSame([
            'fontPairing.heading' => 'Font "Gabarito" (weight 700) is not installed. Check the Google Fonts family name, or install it with: bin/console pw:repurpose:fonts --heading="Gabarito"',
            'fontPairing.body' => 'Font "Poppins" (weight 400) is not installed. Check the Google Fonts family name, or install it with: bin/console pw:repurpose:fonts --body="Poppins"',
        ], $this->violations([...$spec, 'fontPairing' => ['heading' => 'Gabarito', 'body' => 'Poppins']]));

        // A registry pairing too; both its faces report at `fontPairing`, so the
        // path => message map keeps the body's.
        self::assertSame(
            ['fontPairing' => 'Font "Questrial" (weight 400) is not installed. Check the Google Fonts family name, or install it with: bin/console pw:repurpose:fonts --body="Questrial"'],
            $this->violations([...$spec, 'fontPairing' => 'rozha-one-questrial']),
        );
    }

    public function testMalformedFontPairingsAreRejected(): void
    {
        $spec = ['page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5', 'slides' => [['title' => 'Hi']]];

        self::assertSame(['fontPairing' => 'Unknown font pairing.'], $this->violations([...$spec, 'fontPairing' => 'no-such-pairing']));
        self::assertSame(
            ['fontPairing.heading' => 'A font family name holds only letters, digits and spaces, as on Google Fonts.'],
            $this->violations([...$spec, 'fontPairing' => ['heading' => 'Bad/Name', 'body' => 'Inter']]),
        );
        self::assertSame(
            ['fontPairing.body' => 'A custom font pairing needs both a heading and a body family.'],
            $this->violations([...$spec, 'fontPairing' => ['heading' => 'Poppins']]),
        );
    }

    public function testSplitLayoutNeedsTwoImages(): void
    {
        $violations = $this->violations([
            'page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5',
            'slides' => [['title' => 'Split', 'imageLayout' => 'split-v', 'images' => [['media' => '1.jpg']]]],
        ]);

        self::assertArrayHasKey('slides[0].images', $violations);
    }

    public function testSplitLayoutWithTwoImagesPasses(): void
    {
        $violations = $this->violations([
            'page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5',
            'slides' => [['title' => 'Split', 'imageLayout' => 'split-h', 'images' => [['media' => '1.jpg'], ['media' => '2.jpg']]]],
        ]);

        self::assertSame([], $violations);
    }

    public function testTooManySlidesForNetworkIsRejected(): void
    {
        // An organic Pinterest image Pin contains one visual.
        $slides = array_fill(0, 2, ['title' => 'Hi', 'image' => ['media' => '1.jpg']]);
        $violations = $this->violations([
            'page' => 'x', 'network' => 'pinterest', 'format' => 'pinterest-2-3',
            'caption' => 'A useful Pin description.',
            'slides' => $slides,
        ]);

        self::assertArrayHasKey('slides', $violations);
    }

    public function testXAcceptsFourImagesButRejectsFive(): void
    {
        foreach (['x-16-9', 'x-1-1'] as $format) {
            $spec = [
                'page' => 'x', 'network' => 'x', 'format' => $format,
                'slides' => array_fill(0, 4, ['title' => 'Hi']),
            ];
            self::assertSame([], $this->violations($spec));
            $spec['slides'][] = ['title' => 'One too many'];
            self::assertArrayHasKey('slides', $this->violations($spec));
        }
    }

    public function testVideoNetworksRequireTheVerticalFormat(): void
    {
        foreach (['youtube', 'tiktok'] as $network) {
            $spec = [
                'page' => 'x', 'network' => $network, 'format' => 'story-9-16',
                'video' => 'ready.mp4', 'slides' => [['title' => 'Hi']],
            ];
            self::assertSame([], $this->violations($spec));
            $spec['format'] = 'instagram-4-5';
            self::assertArrayHasKey('format', $this->violations($spec));
        }
    }

    public function testPinterestRequiresANonEmptyCaption(): void
    {
        foreach ([null, '', '   '] as $caption) {
            $violations = $this->violations([
                'page' => 'x', 'network' => 'pinterest', 'format' => 'pinterest-2-3',
                'caption' => $caption,
                'slides' => [['title' => 'Hi']],
            ]);

            self::assertArrayHasKey('caption', $violations);
            self::assertStringContainsString('needs a caption', $violations['caption']);
        }
    }

    public function testCaptionLimitIncludesHashtags(): void
    {
        $violations = $this->violations([
            'page' => 'x', 'network' => 'pinterest', 'format' => 'pinterest-2-3',
            'caption' => str_repeat('a', 490),
            'hashtags' => ['walkingholiday'],
            'slides' => [['title' => 'Hi']],
        ]);

        self::assertArrayHasKey('caption', $violations);
        self::assertStringContainsString('500-character limit', $violations['caption']);
    }

    public function testGuidanceDoesNotFailValidation(): void
    {
        // 12 LinkedIn slides breaks the "ideally under 10" *guidance* but no hard
        // limit (cap is 300 pages) — the validator must stay silent.
        $slides = array_fill(0, 12, ['title' => 'Hi', 'image' => ['media' => '1.jpg']]);
        $violations = $this->violations([
            'page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5',
            'slides' => $slides,
        ]);

        self::assertSame([], $violations);
    }
}
