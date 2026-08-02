<?php

namespace Pushword\Core\Twig;

use Doctrine\Common\Collections\Collection;
use Exception;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment as Twig;

class BlockExtension
{
    public function __construct(
        private readonly SiteRegistry $apps,
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
        int|string $size = 0, // bytes
        string $id = '',
    ): string {
        $size = (int) $size;

        try {
            $media = $this->mediaExtension->transformStringToMedia($url);
            $url = '/'.$this->publicMediaDir.'/'.$media->getFileName();
            if (0 === $size) {
                $size = $media->size;
            }
        } catch (Exception) {
            if (! str_starts_with($url, '/') && ! str_starts_with($url, 'http')) {
                $url = '/'.$this->publicMediaDir.'/'.$url;
            }
        }

        $template = $this->apps->get()->getView('/component/attaches.html.twig');

        return $this->twig->render($template, [
            'id' => $id,
            'title' => $title,
            'url' => $url,
            'size' => $size,
        ]);
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
            // Lets the template take page.uniqueGalleryId instead of a random id,
            // keeping re-renders of an unchanged page byte-identical.
            'page' => $this->apps->getCurrentPage(),
        ]);
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
}
