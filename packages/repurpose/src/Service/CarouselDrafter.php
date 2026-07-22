<?php

namespace Pushword\Repurpose\Service;

use Pushword\Core\Entity\Page;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Drafts a starting carousel spec from a page (no AI): the h1 and main image
 * become the cover slide, each `##` section becomes a body slide (its heading as
 * the title, its lead sentence as the paragraph, its first image as the
 * background), and a closing call-to-action slide is appended.
 *
 * It reads the page's Markdown structure and strips Twig/HTML so pages carrying
 * `{{ … }}` calls or raw markup (as on production sites) degrade to clean text
 * rather than leaking markup onto a slide.
 *
 * The result is a valid, editable spec — an agent (or a human in the studio) then
 * rewrites the copy and reframes the images.
 */
final readonly class CarouselDrafter
{
    private const int MAX_BODY_SLIDES = 6;

    public function __construct(
        private FormatRegistry $formats,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function draft(Page $page, string $network = 'linkedin', string $format = 'linkedin-4-5'): array
    {
        if (null === $this->formats->get($format)) {
            $format = 'linkedin-4-5';
        }

        $content = $page->getMainContent();
        $mainImage = $page->getMainImage()?->getFileName();

        $slides = [$this->coverSlide($page, $mainImage)];
        foreach ($this->sections($content) as $section) {
            if (\count($slides) > self::MAX_BODY_SLIDES) {
                break;
            }

            $slides[] = $this->bodySlide($section, $mainImage);
        }

        $slides[] = $this->ctaSlide($page->getLocale());

        return [
            'page' => $page->getSlug(),
            'network' => $network,
            'format' => $format,
            'status' => 'draft',
            'counter' => ['style' => 'dots', 'align' => 'right'],
            'background' => 'dots',
            'slides' => $slides,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function coverSlide(Page $page, ?string $mainImage): array
    {
        $slide = [
            'layout' => 'bottom',
            'align' => 'left',
            'title' => $this->clean($page->getH1()),
            'swipe' => true,
        ];

        if (null !== $mainImage) {
            $slide['image'] = ['media' => $mainImage, 'focusX' => 0.5, 'focusY' => 0.4, 'zoom' => 1.05];
            $slide['overlay'] = 0.45;
        }

        return $slide;
    }

    /**
     * @param array{title: string, text: string, image: ?string} $section
     *
     * @return array<string, mixed>
     */
    private function bodySlide(array $section, ?string $fallbackImage): array
    {
        $image = $section['image'] ?? $fallbackImage;
        $slide = [
            'layout' => null !== $image ? 'bottom' : 'center',
            'align' => null !== $image ? 'left' : 'center',
            'title' => $section['title'],
        ];

        if ('' !== $section['text']) {
            $slide['paragraph'] = $section['text'];
        }

        if (null !== $image) {
            $slide['image'] = ['media' => $image, 'focusX' => 0.5, 'focusY' => 0.5, 'zoom' => 1.0];
            $slide['overlay'] = 0.5;
        }

        return $slide;
    }

    /**
     * The CTA copy follows the page's locale, not the admin's — the slide is
     * published to the page's audience.
     *
     * @return array<string, mixed>
     */
    private function ctaSlide(string $locale): array
    {
        $locale = '' === $locale ? null : $locale;

        return [
            'layout' => 'center',
            'align' => 'center',
            'tagline' => $this->translator->trans('repurpose.draft.ctaTagline', [], 'messages', $locale),
            'title' => $this->translator->trans('repurpose.draft.ctaTitle', [], 'messages', $locale),
        ];
    }

    /**
     * Split Markdown into `##` sections, each with its heading, lead text and
     * first image.
     *
     * @return list<array{title: string, text: string, image: ?string}>
     */
    private function sections(string $content): array
    {
        if (false === preg_match_all('/^##\s+(.+?)\s*$(.*?)(?=^##\s|\z)/ms', $content, $matches, \PREG_SET_ORDER)) {
            return [];
        }

        $sections = [];
        foreach ($matches as $match) {
            $body = $match[2];
            $sections[] = [
                'title' => $this->clean($match[1]),
                'text' => $this->leadSentence($body),
                'image' => $this->firstImage($body),
            ];
        }

        return $sections;
    }

    private function firstImage(string $markdown): ?string
    {
        if (1 === preg_match('/!\[[^\]]*\]\(\s*([^)\s]+)/', $markdown, $m)) {
            return basename($m[1]);
        }

        return null;
    }

    private function leadSentence(string $markdown): string
    {
        foreach (preg_split('/\r\n|\r|\n/', $markdown) ?: [] as $line) {
            $line = trim($line);
            // Skip images, blank lines, list markers and other headings.
            if ('' === $line) {
                continue;
            }

            if (str_starts_with($line, '!')) {
                continue;
            }

            if (str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, '-')) {
                continue;
            }

            $text = $this->clean($line);
            if ('' === $text) {
                continue;
            }

            // Keep it to a single, tight sentence.
            $sentences = preg_split('/(?<=[.!?])\s+/', $text) ?: [$text];

            return $sentences[0];
        }

        return '';
    }

    /**
     * Strip Twig, HTML and Markdown inline syntax down to plain text.
     */
    private function clean(string $value): string
    {
        $value = (string) preg_replace('/\{\{.*?\}\}|\{%.*?%\}|\{#.*?#\}/s', '', $value);
        $value = strip_tags($value);
        $value = (string) preg_replace('/!?\[([^\]]*)\]\([^)]*\)/', '$1', $value); // links/images → label
        $value = (string) preg_replace('/[*_`>#]+/', '', $value); // emphasis/code/quotes/heading marks

        return trim((string) preg_replace('/\s+/', ' ', $value));
    }
}
