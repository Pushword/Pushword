<?php

namespace Pushword\Core\Service\Markdown\Extension\Renderer;

use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Node;
use League\CommonMark\Node\NodeIterator;
use League\CommonMark\Node\StringContainerInterface;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use Pushword\Core\Service\Markdown\BrokenImageComment;
use Pushword\Core\Service\Markdown\Extension\Util\RawHtml;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Twig\MediaExtension;
use Throwable;

final readonly class ImageRenderer implements NodeRendererInterface
{
    public function __construct(
        private MediaExtension $mediaExtension,
        private SiteRegistry $apps,
    ) {
    }

    /** The app property holding the `sizes` body images announce. MarkdownParser keys on it too. */
    public const string SIZES_CONFIG_KEY = 'body_image_sizes';

    /**
     * The `sizes` body images announce for the current site, or null to keep the
     * component default (100vw) — honest, but it over-serves every image the text
     * column renders narrower than the viewport.
     *
     * Read per render rather than captured when the renderer is built: one process
     * serves several hosts (pw:static, worker mode) and this is a per-app value.
     */
    private function bodyImageSizes(): ?string
    {
        $sizes = $this->apps->get()->getStr(self::SIZES_CONFIG_KEY);

        return '' !== $sizes ? $sizes : null;
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): RawHtml
    {
        Image::assertInstanceOf($node);

        /** @var Image $node */
        $src = $node->getUrl();
        $alt = $this->getAltText($node);

        try {
            // A body image links to itself so it opens in the lightbox — unless
            // the author already gave it a destination (`[![alt](img)](/page)`).
            return new RawHtml($this->mediaExtension->renderImage(
                $src,
                htmlspecialchars($alt),
                link: ! $node->parent() instanceof Link,
                sizes: $this->bodyImageSizes(),
            ));
        } catch (Throwable) {
            // A body image that can't be resolved (old Drupal name, deleted file,
            // typo…) must degrade to an invisible marker, never take down the whole
            // page for one `![](…)` among dozens of healthy blocks.
            return new RawHtml(BrokenImageComment::for($src));
        }
    }

    private function getAltText(Image $node): string
    {
        $altText = '';

        foreach ((new NodeIterator($node)) as $n) {
            if ($n instanceof StringContainerInterface) {
                $altText .= $n->getLiteral();
            } elseif ($n instanceof Newline) {
                $altText .= "\n";
            }
        }

        return $altText;
    }
}
