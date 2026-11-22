<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Tests\Service;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Image\ImageCacheGenerator;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Repurpose\Service\CarouselFactory;
use Pushword\Repurpose\Service\ContrastAdvisor;
use Pushword\Repurpose\Service\MediaResolver;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class ContrastAdvisorTest extends KernelTestCase
{
    private string $publicDir;

    private ContrastAdvisor $advisor;

    protected function setUp(): void
    {
        $this->publicDir = sys_get_temp_dir().'/pw-contrast-'.uniqid();
        mkdir($this->publicDir.'/media/md', 0o777, true);
        self::bootKernel();
        $this->advisor = new ContrastAdvisor(new MediaResolver(
            $this->publicDir.'/media',
            self::getContainer()->get(MediaRepository::class),
            self::getContainer()->get(ImageCacheGenerator::class),
        ));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        array_map(unlink(...), glob($this->publicDir.'/media/md/*') ?: []);
        rmdir($this->publicDir.'/media/md');
        rmdir($this->publicDir.'/media');
        rmdir($this->publicDir);
    }

    /**
     * @param array<string, mixed> $spec
     *
     * @return list<array{path: string, message: string}>
     */
    private function warnings(array $spec): array
    {
        return $this->advisor->warnings(new CarouselFactory()->fromArray($spec));
    }

    /**
     * @param array<string, mixed> $slide
     *
     * @return array<string, mixed>
     */
    private function spec(array $slide): array
    {
        return ['page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5', 'slides' => [$slide]];
    }

    /**
     * @param int<0, 255> $r
     * @param int<0, 255> $g
     * @param int<0, 255> $b
     */
    private function writeImage(string $name, int $r, int $g, int $b): void
    {
        $image = imagecreatetruecolor(64, 64);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, $r, $g, $b));
        imagepng($image, $this->publicDir.'/media/md/'.$name);
    }

    public function testDefaultPaletteRaisesNoWarning(): void
    {
        self::assertSame([], $this->warnings($this->spec(['title' => 'Hello'])));
    }

    public function testToneOnToneFlatPaletteIsFlagged(): void
    {
        $warnings = $this->warnings($this->spec([
            'title' => 'Hello',
            'palette' => ['bg' => '#0b1120', 'text' => '#1e293b'],
        ]));

        self::assertCount(1, $warnings);
        self::assertSame('slides[0].title', $warnings[0]['path']);
        self::assertStringContainsString('contrast', $warnings[0]['message']);
    }

    /**
     * The reported first-run failure mode: a dark `palette.text` applied verbatim
     * over a photo — {valid: true} but unreadable. The advisor must catch it.
     */
    public function testDarkTextOverAnImageIsFlagged(): void
    {
        $this->writeImage('photo.png', 90, 90, 90);

        $warnings = $this->warnings($this->spec([
            'title' => 'Hello',
            'palette' => ['text' => '#1a202c'],
            'image' => ['media' => 'photo.png'],
            'overlay' => 0,
        ]));

        self::assertCount(1, $warnings);
        self::assertStringContainsString('photo.png', $warnings[0]['message']);
        self::assertStringContainsString('lighter', $warnings[0]['message']);
    }

    public function testLightTextOverADarkenedImagePasses(): void
    {
        $this->writeImage('photo.png', 120, 120, 120);

        self::assertSame([], $this->warnings($this->spec([
            'title' => 'Hello',
            'image' => ['media' => 'photo.png'],
            // No explicit overlay: the factory's 0.35 default applies.
        ])));
    }

    public function testDarkTextOverABrightImagePasses(): void
    {
        $this->writeImage('bright.png', 245, 245, 245);

        self::assertSame([], $this->warnings($this->spec([
            'title' => 'Hello',
            'palette' => ['text' => '#0f172a'],
            'image' => ['media' => 'bright.png'],
            'overlay' => 0,
        ])));
    }

    public function testDeckPaletteInheritedBySlideIsFlagged(): void
    {
        $warnings = $this->advisor->warnings(new CarouselFactory()->fromArray([
            'page' => 'x', 'network' => 'linkedin', 'format' => 'linkedin-4-5',
            'palette' => ['bg' => '#0b1120', 'text' => '#1e293b'],
            'slides' => [['title' => 'Hello']],
        ]));

        self::assertCount(1, $warnings);
    }

    public function testDarkTextOnMissingMediaPlaceholderIsFlagged(): void
    {
        $warnings = $this->warnings($this->spec([
            'title' => 'Hello',
            'palette' => ['text' => '#111827'],
            'image' => ['media' => 'nowhere.png'],
        ]));

        self::assertCount(1, $warnings);
        self::assertStringContainsString('missing-media placeholder', $warnings[0]['message']);
    }

    /**
     * The reported miss: a cream free text over a cream background passed, since
     * only the layout stack was measured.
     */
    public function testToneOnToneFreeTextIsFlagged(): void
    {
        $warnings = $this->warnings($this->spec([
            'palette' => ['bg' => '#f5efe0'],
            'texts' => [
                ['content' => 'Readable', 'color' => '#1c1917'],
                ['content' => 'Cream on cream', 'color' => '#efe6d2'],
            ],
        ]));

        self::assertCount(1, $warnings);
        self::assertSame('slides[0].texts[1]', $warnings[0]['path']);
        self::assertStringContainsString('#efe6d2 over background #f5efe0', $warnings[0]['message']);
    }

    public function testFreeTextInheritsTheSlideTextColour(): void
    {
        $warnings = $this->warnings($this->spec([
            'palette' => ['bg' => '#f5efe0', 'text' => '#f8fafc'],
            'texts' => [['content' => 'Inherited']],
        ]));

        self::assertSame(['slides[0].texts[0]'], array_column($warnings, 'path'));
    }

    public function testTaglineIsMeasuredInTheAccentColour(): void
    {
        $warnings = $this->warnings($this->spec([
            'tagline' => 'Kicker',
            'title' => 'Hello',
            'palette' => ['bg' => '#0b1120', 'text' => '#f8fafc', 'accent' => '#1e293b'],
        ]));

        self::assertSame(['slides[0].tagline'], array_column($warnings, 'path'));
        self::assertStringContainsString('#1e293b', $warnings[0]['message']);
    }

    public function testHighlightedTextIsMeasuredAgainstItsMarker(): void
    {
        $slide = [
            'title' => 'Marked',
            'highlight' => '#fde047',
            'palette' => ['bg' => '#0b1120', 'text' => '#0f172a'],
        ];
        self::assertSame([], $this->warnings($this->spec($slide)), 'dark text on a yellow marker reads, whatever the background');

        $slide['texts'] = [['content' => 'Marked', 'color' => '#fef9c3', 'highlight' => '#fde047']];
        $warnings = $this->warnings($this->spec($slide));
        self::assertSame(['slides[0].texts[0]'], array_column($warnings, 'path'));
        self::assertStringContainsString('highlight #fde047', $warnings[0]['message']);
    }

    public function testParagraphIsMeasuredInTheSlideTextColour(): void
    {
        $warnings = $this->warnings($this->spec([
            'paragraph' => 'Body copy',
            'palette' => ['bg' => '#0b1120', 'text' => '#1e293b'],
        ]));

        self::assertSame(['slides[0].paragraph'], array_column($warnings, 'path'));
    }

    public function testTextlessSlideIsIgnored(): void
    {
        self::assertSame([], $this->warnings($this->spec([
            'palette' => ['bg' => '#0b1120', 'text' => '#0b1120'],
            'image' => ['media' => 'missing.png'],
        ])));
    }
}
