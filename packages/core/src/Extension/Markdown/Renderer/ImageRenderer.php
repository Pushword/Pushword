<?php

namespace Pushword\Core\Extension\Markdown\Renderer;

use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Node;
use League\CommonMark\Node\NodeIterator;
use League\CommonMark\Node\StringContainerInterface;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Extension\Markdown\Util\RawHtml;
use Stringable;
use Twig\Environment;

/**
 * Renderer personnalisé pour les images.
 * Utilise le template Twig pour un rendu personnalisé.
 */
final readonly class ImageRenderer implements NodeRendererInterface
{
    public function __construct(
        private Environment $twig,
        private AppPool $apps
    ) {
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): Stringable
    {
        Image::assertInstanceOf($node);

        /** @var Image $node */
        $src = $node->getUrl();
        $alt = $this->getAltText($node);

        // Rendre l'image via le template Twig
        $html = $this->twig->render(
            $this->apps->get()->getView('/component/image_inline.html.twig'),
            [
                'image_src' => $src,
                'image_alt' => htmlspecialchars($alt),
                'image_link' => null,
                'image_link_attr' => null,
                'image_link_obf' => false,
            ]
        );

        return new RawHtml($html);
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
