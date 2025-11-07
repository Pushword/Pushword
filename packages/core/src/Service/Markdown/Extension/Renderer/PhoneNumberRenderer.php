<?php

namespace Pushword\Core\Service\Markdown\Extension\Renderer;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use Pushword\Core\Service\LinkProvider;
use Pushword\Core\Service\Markdown\Extension\Node\PhoneNumber;
use Pushword\Core\Service\Markdown\Extension\Util\RawHtml;
use Stringable;

/**
 * Renderer pour les numéros de téléphone.
 */
final readonly class PhoneNumberRenderer implements NodeRendererInterface
{
    public function __construct(
        private LinkProvider $linkProvider
    ) {
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): Stringable
    {
        PhoneNumber::assertInstanceOf($node);

        /** @var PhoneNumber $node */
        $number = $node->getNumber();

        // Utiliser le LinkProvider pour rendre le numéro de téléphone
        $html = $this->linkProvider->renderPhoneNumber(trim($number));

        return new RawHtml($html);
    }
}
