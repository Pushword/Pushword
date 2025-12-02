<?php

namespace Pushword\Core\Twig;

use Doctrine\Common\Collections\Collection;
use Exception;
use Pushword\Core\Component\App\AppPool;

use function Safe\json_encode;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment as Twig;

/**
 * @template T of object
 */
class BlockExtension
{
    public function __construct(
        private readonly AppPool $apps,
        public Twig $twig,
        private readonly MediaExtension $mediaExtension,
        #[Autowire('%pw.public_media_dir%')]
        private readonly string $publicMediaDir,
    ) {
    }

    #[AsTwigFunction('attaches', needsEnvironment: false, isSafe: ['html'])]
    public function renderAttaches(
        string $title,
        string $url,
        int $size, // bytes
        string $id = '',
    ): string {
        $url = $this->mediaExtension->transformStringToMedia($url);
        $url = '/'.$this->publicMediaDir.'/'.$url->getFileName();

        $template = $this->apps->get()->getView('/component/attaches.html.twig');

        $html = $this->twig->render($template, [
            'id' => $id,
            'title' => $title,
            'url' => $url,
            'size' => $size,
        ]);

        return $html;
    }

    /**
     * @param array<mixed>|Collection<int, mixed> $images is very tolerant, most of the time it's an array of string corresponding to the mediaName (eg: ['filename.jpg', 'filename2.jpg'])
     * @param int                                 $pos    set to < 3 permit to disable lazy loading on first image
     */
    #[AsTwigFunction('gallery', needsEnvironment: false, isSafe: ['html'])]
    public function renderGallery(
        array|Collection $images,
        ?string $gridCols = null,
        ?string $imageFilter = null,
        bool $clickable = true,
        int $pos = 100
    ): string {
        // @see ./../templates/component/images_gallery.html.twig
        $template = $this->apps->get()->getView('/component/images_gallery.html.twig');

        return $this->twig->render($template, [
            'images' => $images,
            'grid_cols' => $gridCols,
            'image_filter' => $imageFilter,
            'pos' => $pos,
            'clickable' => $clickable,
        ]);
    }

    /**
     * @return array{tunes: array{anchor: string, textAlign: string, class: string} } $block
     */
    private function normalizeBlock(object $block): array
    {
        $block = json_decode(json_encode($block), true);

        if (! \is_array($block)) {
            throw new Exception('Block must be an array');
        }

        if (! isset($block['tunes'])) {
            $block['tunes'] = [];
        }

        if (! is_array($block['tunes'])) {
            throw new Exception('Tunes must be an array');
        }

        if (! isset($block['tunes']['anchor'])) {
            $block['tunes']['anchor'] = '';
        }

        if (! isset($block['tunes']['textAlign'])) {
            $block['tunes']['textAlign'] = '';
        }

        if (isset($block['data']) && is_array($block['data']) && isset($block['data']['alignment']) && is_string($block['data']['alignment'])) {
            $block['tunes']['textAlign'] = $block['data']['alignment'];
        }

        return $block; // @phpstan-ignore-line
    }

    /**
     * @param array<mixed> $additionalAttrs
     */
    #[AsTwigFunction('blockWrapperAttr', needsEnvironment: false, isSafe: ['html'])]
    public function blockWrapperAttr(object $block, array $additionalAttrs = []): string
    {
        $block = $this->normalizeBlock($block);
        $attrs = $additionalAttrs;

        if ('' !== $block['tunes']['anchor']) {
            $attrs['id'] = $block['tunes']['anchor'];
        }

        $class = isset($attrs['class']) && is_string($attrs['class']) ? $attrs['class'] : '';

        // Handle custom classes
        if ('' !== $block['tunes']['class']) {
            $class = $class.' '.$block['tunes']['class'];
        }

        // Handle alignment
        if (in_array($block['tunes']['textAlign'], ['center', 'right'], true)) {
            $alignmentClass = 'text-'.$block['tunes']['textAlign'];
            $class = $class.' '.$alignmentClass;
        }

        if ('' !== $class) {
            $attrs['class'] = $class;
        }

        // Build attributes string
        $attrString = '';
        foreach ($attrs as $key => $value) {
            if ('' !== $value && is_string($value) && is_string($key)) {
                $attrString .= ' '.$key.'="'.htmlspecialchars($value).'"';
            }
        }

        return $attrString;
    }

    /**
     * Generate block wrapper ID for EditorJS blocks.
     *
     * @param string $anchor - Anchor ID
     *
     * @return string - HTML id attribute
     */
    #[AsTwigFunction('blockWrapperId', needsEnvironment: false, isSafe: ['html'])]
    public function blockWrapperId(string $anchor = ''): string
    {
        return '' !== $anchor ? ' id="'.htmlspecialchars($anchor).'"' : '';
    }

    /**
     * Generate block wrapper alignment class for EditorJS blocks.
     *
     * @param string $alignment - Alignment value
     *
     * @return string - CSS class string
     */
    #[AsTwigFunction('blockWrapperAlignment', needsEnvironment: false, isSafe: ['html'])]
    public function blockWrapperAlignment(string $alignment = ''): string
    {
        if ('' === $alignment || 'left' === $alignment) {
            return '';
        }

        $alignmentClass = 'center' === $alignment ? 'text-center' :
                         ('right' === $alignment ? 'text-right' : '');

        return '' !== $alignmentClass ? ' class="'.$alignmentClass.'"' : '';
    }

    /**
     * Convert array of media names to legacy image array format.
     *
     * @param array<mixed> $images - Array of media names
     *
     * @return mixed[][] - Legacy image array format
     */
    #[AsTwigFunction('legacyImageArray', needsEnvironment: false, isSafe: ['html'])]
    public function legacyImageArray(array $images): array
    {
        return array_map(fn ($mediaName): array => ['media' => $mediaName], $images);
    }
}
