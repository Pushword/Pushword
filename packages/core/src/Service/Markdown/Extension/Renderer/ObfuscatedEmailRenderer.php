<?php

namespace Pushword\Core\Service\Markdown\Extension\Renderer;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use Pushword\Core\Service\LinkProvider;
use Pushword\Core\Service\Markdown\Extension\Node\ObfuscatedEmail;
use Pushword\Core\Service\Markdown\Extension\Util\RawHtml;
use Stringable;

/**
 * Renderer pour les emails obfusqués.
 */
final readonly class ObfuscatedEmailRenderer implements NodeRendererInterface
{
    public function __construct(
        private LinkProvider $linkProvider
    ) {
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): Stringable
    {
        ObfuscatedEmail::assertInstanceOf($node);

        /** @var ObfuscatedEmail $node */
        // Extraire l'email depuis l'URL (mailto:email@example.com)
        $email = str_replace('mailto:', '', $node->getUrl());

        // Utiliser le LinkProvider pour rendre l'email obfusqué
        $html = $this->linkProvider->renderEncodedMail($email);

        return new RawHtml($html);
    }
}
