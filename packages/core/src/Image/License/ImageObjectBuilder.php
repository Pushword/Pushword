<?php

namespace Pushword\Core\Image\License;

use Pushword\Core\Entity\Media;
use Pushword\Core\Image\ImageCacheManager;
use Pushword\Core\Site\SiteRegistry;

use function Safe\json_encode;

use Twig\Attribute\AsTwigFunction;

/**
 * Builds the schema.org ImageObject Google reads to award the "Licensable" badge.
 *
 * Emitted next to the <picture> it describes, once per rendered instance, straight
 * from the media's own properties — see component/image.html.twig.
 */
final readonly class ImageObjectBuilder
{
    public function __construct(
        private ImageCacheManager $imageCacheManager,
        private SiteRegistry $apps,
    ) {
    }

    #[AsTwigFunction('imageLicenseJsonLd', isSafe: ['html'])]
    public function render(Media $media): string
    {
        $imageObject = $this->build($media);

        if ([] === $imageObject) {
            return '';
        }

        // JSON_HEX_TAG is what breadcrumbJsonLd does not need and this does: those
        // values go through strip_tags(), these are editor- *and* file-supplied, so an
        // uploaded image's XMP is an injection vector. Escaping `<` is what stops a
        // creator name from closing the block; slashes can then stay readable.
        return '<script type="application/ld+json">'
            .json_encode($imageObject, \JSON_HEX_TAG | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE)
            .'</script>';
    }

    /**
     * @return array<string, mixed> empty when the media cannot qualify
     */
    public function build(Media $media): array
    {
        if (! $media->isImage()) {
            return [];
        }

        $values = MediaLicense::extract($media);

        // contentUrl plus at least one of creator, creditText, copyrightNotice,
        // license. An acquireLicensePage on its own does not make an image eligible.
        if (! $this->meetsGoogleMinimum($values)) {
            return [];
        }

        $imageObject = [
            '@context' => 'https://schema.org',
            '@type' => 'ImageObject',
            'contentUrl' => $this->contentUrl($media),
        ];

        if ('' !== ($name = trim($media->getAlt()))) {
            $imageObject['name'] = $name;
        }

        if (null !== $media->getWidth() && null !== $media->getHeight()) {
            $imageObject['width'] = $media->getWidth();
            $imageObject['height'] = $media->getHeight();
        }

        foreach ([MediaLicense::CREDIT_TEXT, MediaLicense::COPYRIGHT_NOTICE, MediaLicense::LICENSE, MediaLicense::ACQUIRE_LICENSE_PAGE] as $key) {
            if (isset($values[$key])) {
                $imageObject[$key] = $values[$key];
            }
        }

        $creator = $this->creatorNodes($values);
        if ([] !== $creator) {
            // A single creator stays a bare object — both shapes are valid JSON-LD,
            // and the object is what Google's own example shows.
            $imageObject[MediaLicense::CREATOR] = 1 === \count($creator) ? $creator[0] : $creator;
        }

        // digitalSourceType is deliberately absent: no schema.org property on
        // ImageObject carries it, so inventing one would publish noise. It is stored
        // for editorial use, and Google reads provenance from the file itself.

        return $imageObject;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function meetsGoogleMinimum(array $values): bool
    {
        return array_any(MediaLicense::GOOGLE_MINIMUM_KEYS, static fn (string $key): bool => isset($values[$key]) && [] !== $values[$key]);
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<int, array<string, string>>
     */
    private function creatorNodes(array $values): array
    {
        $nodes = [];

        foreach (MediaLicense::normalizeCreators($values[MediaLicense::CREATOR] ?? null) as $creator) {
            $nodes[] = ['@type' => $creator['type'], 'name' => $creator['name']];
        }

        return $nodes;
    }

    private function contentUrl(Media $media): string
    {
        $app = $this->apps->get();
        $base = $app->getStr('base_live_url');

        if ('' === $base) {
            $base = $app->baseUrl;
        }

        // The source format, not the preferred modern one: `default/photo.webp` is
        // offered nowhere in the markup (the <source srcset> lists the breakpoint
        // filters), while `default/photo.jpg` is the <img src>. Google associates the
        // ImageObject with the image it crawled on the page, so contentUrl has to be
        // that exact URL — see component/image.html.twig, which builds it the same way.
        return rtrim($base, '/').$this->imageCacheManager->getBrowserPath($media, 'default', $media->getExtension());
    }
}
