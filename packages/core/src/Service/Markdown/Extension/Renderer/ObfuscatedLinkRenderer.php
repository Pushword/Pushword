<?php

namespace Pushword\Core\Service\Markdown\Extension\Renderer;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use Pushword\Core\Service\LinkProvider;
use Pushword\Core\Service\Markdown\Extension\Node\ObfuscatedLink;
use Pushword\Core\Service\Markdown\Extension\Util\RawHtml;

/**
 * Renderer pour les liens obfusqués.
 */
final readonly class ObfuscatedLinkRenderer implements NodeRendererInterface
{
    public function __construct(
        private LinkProvider $linkProvider
    ) {
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): RawHtml
    {
        ObfuscatedLink::assertInstanceOf($node);

        /** @var ObfuscatedLink $node */
        // Récupérer les attributs
        $attr = $node->getAttributes();

        if (null !== $node->getAttributeClass()) {
            $attr['class'] = $node->getAttributeClass();
        }

        if (null !== $node->getAttributeId()) {
            $attr['id'] = $node->getAttributeId();
        }

        // Récupérer le texte du lien
        $anchor = $childRenderer->renderNodes($node->children());

        // Utiliser le LinkProvider pour rendre le lien obfusqué
        $html = $this->linkProvider->renderLink(
            $anchor,
            $node->getUrl(),
            $attr,
            true // obfuscate = true
        );

        return new RawHtml($html);
    }
}
