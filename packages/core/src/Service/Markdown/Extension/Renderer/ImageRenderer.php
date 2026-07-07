<?php

namespace Pushword\Core\Service\Markdown\Extension\Renderer;

use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Node;
use League\CommonMark\Node\NodeIterator;
use League\CommonMark\Node\StringContainerInterface;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use Pushword\Core\Service\Markdown\BrokenImageComment;
use Pushword\Core\Service\Markdown\Extension\Util\RawHtml;
use Pushword\Core\Twig\MediaExtension;
use Throwable;

final readonly class ImageRenderer implements NodeRendererInterface
{
    public function __construct(
        private MediaExtension $mediaExtension,
    ) {
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): RawHtml
    {
        Image::assertInstanceOf($node);

        /** @var Image $node */
        $src = $node->getUrl();
        $alt = $this->getAltText($node);

        try {
            return new RawHtml($this->mediaExtension->renderImage($src, htmlspecialchars($alt)));
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
