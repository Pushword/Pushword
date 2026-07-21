<?php

namespace Pushword\Repurpose\Service;

use Pushword\Repurpose\Model\Carousel;
use Pushword\Repurpose\Model\Counter;
use Pushword\Repurpose\Model\Palette;
use Pushword\Repurpose\Model\Slide;
use Pushword\Repurpose\Model\SlideImage;

/**
 * Hydrates a decoded-JSON array into a {@see Carousel} object graph. Tolerant by
 * design: missing or wrong-typed values fall back to defaults so the validator can
 * report precise, human-readable violations instead of the factory fataling.
 */
final class CarouselFactory
{
    /**
     * @param array<string, mixed> $data
     */
    public function fromArray(array $data): Carousel
    {
        return new Carousel(
            page: $this->str($data['page'] ?? null),
            network: $this->str($data['network'] ?? null),
            format: $this->str($data['format'] ?? null),
            template: $this->strOr($data['template'] ?? null, 'editorial'),
            status: $this->strOr($data['status'] ?? null, 'draft'),
            plannedAt: $this->strOrNull($data['plannedAt'] ?? null),
            caption: $this->strOrNull($data['caption'] ?? null),
            hashtags: $this->strings($data['hashtags'] ?? null),
            palette: $this->palette($data['palette'] ?? null),
            fontPairing: $this->strOrNull($data['fontPairing'] ?? null),
            counter: $this->counter($data['counter'] ?? null),
            creator: $this->creatorKey($data['creator'] ?? null),
            creatorOrientation: $this->strOr($data['creatorOrientation'] ?? null, 'horizontal'),
            creatorOnSlides: $this->strOr($data['creatorOnSlides'] ?? null, 'intro-outro'),
            slides: $this->slides($data['slides'] ?? null),
        );
    }

    /**
     * @return Slide[]
     */
    private function slides(mixed $raw): array
    {
        if (! \is_array($raw)) {
            return [];
        }

        $slides = [];
        foreach ($raw as $slide) {
            if (! \is_array($slide)) {
                continue;
            }

            $slides[] = new Slide(
                layout: $this->strOr($slide['layout'] ?? null, 'bottom'),
                align: $this->strOr($slide['align'] ?? null, 'left'),
                tagline: $this->strOrNull($slide['tagline'] ?? null),
                title: $this->strOrNull($slide['title'] ?? null),
                paragraph: $this->strOrNull($slide['paragraph'] ?? null),
                swipe: (bool) ($slide['swipe'] ?? false),
                // An image slide with no stated overlay gets a legibility-safe
                // one by default; an explicit 0 is honoured (and the contrast
                // advisor will flag it when it hurts).
                overlay: $this->float($slide['overlay'] ?? null, \is_array($slide['image'] ?? null) ? 0.35 : 0.0),
                textScale: $this->float($slide['textScale'] ?? null, 1.0),
                background: $this->strOr($slide['background'] ?? null, 'none'),
                palette: $this->palette($slide['palette'] ?? null),
                image: $this->image($slide['image'] ?? null),
            );
        }

        return $slides;
    }

    private function image(mixed $raw): ?SlideImage
    {
        if (! \is_array($raw)) {
            return null;
        }

        return new SlideImage(
            media: $this->str($raw['media'] ?? null),
            focusX: $this->float($raw['focusX'] ?? null, 0.5),
            focusY: $this->float($raw['focusY'] ?? null, 0.5),
            zoom: $this->float($raw['zoom'] ?? null, 1.0),
        );
    }

    private function palette(mixed $raw): ?Palette
    {
        if (! \is_array($raw)) {
            return null;
        }

        $palette = new Palette(
            bg: $this->strOrNull($raw['bg'] ?? null),
            text: $this->strOrNull($raw['text'] ?? null),
            accent: $this->strOrNull($raw['accent'] ?? null),
        );

        return $palette->isEmpty() ? null : $palette;
    }

    private function counter(mixed $raw): ?Counter
    {
        if (! \is_array($raw)) {
            return null;
        }

        return new Counter(
            style: $this->strOr($raw['style'] ?? null, 'fraction'),
            align: $this->strOr($raw['align'] ?? null, 'right'),
        );
    }

    /**
     * `creator` may be a registry key (string) or an inline object (one-off); the
     * inline form is resolved by the CreatorRegistry in P3. Here we only keep the
     * key form — an inline object is passed through as its `name` for now.
     */
    private function creatorKey(mixed $raw): ?string
    {
        if (\is_string($raw) && '' !== $raw) {
            return $raw;
        }

        if (\is_array($raw) && \is_string($raw['name'] ?? null) && '' !== $raw['name']) {
            return $raw['name'];
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function strings(mixed $raw): array
    {
        if (! \is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $value) {
            if (\is_string($value) && '' !== $value) {
                $out[] = $value;
            }
        }

        return $out;
    }

    private function str(mixed $value): string
    {
        if (\is_string($value)) {
            return $value;
        }

        return \is_scalar($value) ? (string) $value : '';
    }

    /**
     * The scalar's string value, or the default when it is absent or empty.
     */
    private function strOr(mixed $value, string $default): string
    {
        $string = $this->str($value);

        return '' !== $string ? $string : $default;
    }

    private function strOrNull(mixed $value): ?string
    {
        return \is_string($value) && '' !== $value ? $value : null;
    }

    private function float(mixed $value, float $default): float
    {
        return is_numeric($value) ? (float) $value : $default;
    }
}
