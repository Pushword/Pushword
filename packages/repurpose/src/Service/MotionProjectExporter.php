<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Service;

use Pushword\Repurpose\Model\Carousel;
use Pushword\Repurpose\Model\Creator;

use function Safe\file_get_contents;
use function Safe\json_encode;

use Symfony\Component\Finder\Finder;

/** Exports source files only. Agent-authored React executes in the exported project. */
final readonly class MotionProjectExporter
{
    public function __construct(
        private SlideRenderer $renderer,
        private FormatRegistry $formats,
        private AudioAssetResolver $audioAssets,
    ) {
    }

    /**
     * @param array<string, mixed> $spec the original spec, kept for further authoring
     *
     * @return array<string, string> relative file path => bytes
     */
    public function files(Carousel $carousel, array $spec, ?Creator $creator = null): array
    {
        $files = [];
        foreach (new Finder()->files()->ignoreDotFiles(false)->sortByName()->in(__DIR__.'/../Resources/motion') as $file) {
            $files[$file->getRelativePathname()] = file_get_contents($file->getPathname());
        }

        $slides = [];
        foreach (array_keys($carousel->slides) as $index) {
            $id = 'slide-'.($index + 1);
            $layers = [];
            foreach ($this->renderer->renderSlideLayers($carousel, $index, $creator) as $name => $svg) {
                $src = 'slides/'.$id.'/'.$name.'.svg';
                $files['public/'.$src] = $svg;
                $isText = \in_array($name, ['tagline', 'title', 'paragraph'], true) || str_starts_with($name, 'texts.');
                $layers[] = ['id' => $name, 'src' => $src, 'animation' => $isText ? 'rise' : 'none'];
            }

            $slides[] = ['id' => $id, 'durationInFrames' => 90, 'transitionInFrames' => 0 === $index ? 0 : 12, 'layers' => $layers];
        }

        $timeline = [
            '$schema' => './timeline.schema.json',
            'width' => $this->formats->width($carousel->format),
            'height' => $this->formats->height($carousel->format),
            'fps' => 30,
            'slides' => $slides,
        ];
        if (null !== $carousel->audio) {
            $audio = ['ducking' => $carousel->audio->ducking];
            foreach (['voice', 'music'] as $name) {
                $track = $carousel->audio->{$name};
                if (null === $track) {
                    continue;
                }

                $src = 'audio/'.$name.'.'.strtolower(pathinfo($track->media, \PATHINFO_EXTENSION));
                $files['public/'.$src] = $this->audioAssets->read($track);
                $audio[$name] = ['src' => $src, 'volume' => $track->volume, 'fadeIn' => $track->fadeIn, 'fadeOut' => $track->fadeOut];
            }

            $timeline['audio'] = $audio;
        }

        $files['timeline.json'] = json_encode($timeline, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE)."\n";
        $files['spec.json'] = json_encode($spec, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE)."\n";
        $files['caption.txt'] = CaptionFormatter::format($carousel->caption, array_values($carousel->hashtags))."\n";

        return $files;
    }
}
