<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Tests\Service;

use DOMDocument;
use DOMElement;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Repurpose\Model\Creator;
use Pushword\Repurpose\Service\CarouselFactory;
use Pushword\Repurpose\Service\MediaResolver;
use Pushword\Repurpose\Service\MotionProjectExporter;
use Pushword\Repurpose\Service\SlideRenderer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

#[Group('integration')]
final class MotionProjectExporterTest extends KernelTestCase
{
    public function testLayersRecomposeTheStaticSlideWithoutChangingArtwork(): void
    {
        self::bootKernel();
        $renderer = self::getContainer()->get(SlideRenderer::class);
        $carousel = new CarouselFactory()->fromArray([
            'page' => 'x', 'network' => 'instagram', 'format' => 'story-9-16',
            'background' => 'sketchy', 'counter' => ['style' => 'bar'],
            'slides' => [[
                'title' => 'Hello & <world>', 'highlight' => '#ffeebb', 'paragraph' => 'Body',
                'imageLayout' => 'split-v', 'images' => [['media' => 'missing-a.jpg'], ['media' => 'missing-b.jpg']],
                'texts' => [['content' => 'Note']], 'swipe' => true,
            ], ['title' => 'Outro']],
        ]);
        $creator = new Creator('Test Author');
        $layers = $renderer->renderSlideLayers($carousel, 0, $creator);
        self::assertSame(['background', 'images.0', 'images.1', 'overlay', 'effect', 'title', 'paragraph', 'texts.0', 'creator', 'counter', 'swipe'], array_keys($layers));
        $body = '';
        foreach ($layers as $svg) {
            self::assertStringContainsString('data:font/ttf;base64,', $svg);
            self::assertStringContainsString('id="frame-0"', $svg);
            $body .= $this->body($svg);
        }

        self::assertSame($this->body($renderer->renderSlide($carousel, 0, $creator)), $body);
        self::assertSame([], $renderer->renderSlideLayers($carousel, 99));
    }

    private function body(string $svg): string
    {
        $document = new DOMDocument();
        self::assertTrue($document->loadXML($svg));
        $root = $document->documentElement;
        self::assertInstanceOf(DOMElement::class, $root);
        $body = '';
        foreach ($root->childNodes as $index => $node) {
            if (0 !== $index) {
                $body .= $document->saveXML($node);
            }
        }

        return $body;
    }

    public function testImageCreatorAndEffectLayersKeepTheirLocalDefinitions(): void
    {
        self::bootKernel();
        $directory = sys_get_temp_dir().'/pw-motion-image-'.bin2hex(random_bytes(6));
        mkdir($directory.'/default', recursive: true);
        imagepng(imagecreatetruecolor(4, 4), $directory.'/default/pixel.png');

        try {
            self::getContainer()->set(MediaResolver::class, new MediaResolver($directory));
            $renderer = self::getContainer()->get(SlideRenderer::class);
            $carousel = new CarouselFactory()->fromArray([
                'page' => 'x', 'network' => 'instagram', 'format' => 'story-9-16',
                'background' => 'paper', 'slides' => [[
                    'title' => 'Image', 'imageLayout' => 'split-h',
                    'images' => [['media' => 'pixel.png'], ['media' => 'pixel.png']],
                ]],
            ]);
            $layers = $renderer->renderSlideLayers($carousel, 0, new Creator('Jane Doe', avatar: 'pixel.png'));
            foreach (['images.0' => 'frame-0-0', 'images.1' => 'frame-0-1', 'creator' => 'rp-av-0'] as $layer => $clip) {
                self::assertStringContainsString('id="'.$clip.'"', $layers[$layer]);
                self::assertStringContainsString('url(#'.$clip.')', $layers[$layer]);
                self::assertStringContainsString('data:image/png;base64,', $layers[$layer]);
                $this->body($layers[$layer]);
            }

            self::assertStringContainsString('id="rp-paper-0"', $layers['effect']);
            self::assertStringContainsString('url(#rp-paper-0)', $layers['effect']);
            $this->body($layers['effect']);
        } finally {
            new Filesystem()->remove($directory);
        }
    }

    public function testExportIsDeterministicAndIncludesACompleteLocalProject(): void
    {
        self::bootKernel();
        $exporter = self::getContainer()->get(MotionProjectExporter::class);
        $spec = [
            'page' => 'x', 'network' => 'instagram', 'format' => 'story-9-16',
            'caption' => 'A caption', 'hashtags' => ['motion'],
            'slides' => [['title' => 'Hello'], ['title' => 'Bye']],
        ];
        $carousel = new CarouselFactory()->fromArray($spec);
        $files = $exporter->files($carousel, $spec);
        self::assertSame($files, $exporter->files($carousel, $spec));
        foreach (['package.json', '.gitignore', 'README.md', 'src/index.jsx', 'src/Scene.jsx', 'src/scenes.jsx', 'src/timeline.mjs', 'src/check.mjs', 'timeline.schema.json'] as $path) {
            self::assertArrayHasKey($path, $files);
        }

        self::assertSame($spec, json_decode($files['spec.json'], true));
        self::assertSame("A caption\n\n#motion\n", $files['caption.txt']);
        $timeline = json_decode($files['timeline.json'], true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($timeline);
        self::assertSame(1080, $timeline['width']);
        self::assertSame(1920, $timeline['height']);
        self::assertSame(30, $timeline['fps']);
        self::assertIsArray($timeline['slides']);
        self::assertCount(2, $timeline['slides']);
        foreach ($timeline['slides'] as $index => $slide) {
            self::assertIsArray($slide);
            self::assertSame(90, $slide['durationInFrames']);
            self::assertSame(0 === $index ? 0 : 12, $slide['transitionInFrames']);
            self::assertIsArray($slide['layers']);
            foreach ($slide['layers'] as $layer) {
                self::assertIsArray($layer);
                self::assertIsString($layer['src']);
                self::assertArrayHasKey('public/'.$layer['src'], $files);
                self::assertSame('title' === $layer['id'] ? 'rise' : 'none', $layer['animation']);
            }
        }
    }
}
