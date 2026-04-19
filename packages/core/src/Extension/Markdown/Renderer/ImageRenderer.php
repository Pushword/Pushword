<?php

namespace Pushword\Core\Extension\Markdown\Renderer;

use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Node;
use League\CommonMark\Node\NodeIterator;
use League\CommonMark\Node\StringContainerInterface;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use Pushword\Core\Extension\Markdown\Util\RawHtml;
use Pushword\Core\Twig\MediaExtension;

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

        return new RawHtml($this->mediaExtension->renderImage($src, htmlspecialchars($alt)));
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
