<?php

namespace Pushword\Core\Service\Markdown\Extension\Renderer;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use Pushword\Core\Service\Markdown\Extension\Node\Notice;
use Pushword\Core\Service\Markdown\Extension\Util\RawHtml;
use Pushword\Core\Site\SiteRegistry;
use Twig\Environment as Twig;

final readonly class NoticeRenderer implements NodeRendererInterface
{
    public function __construct(
        private Twig $twig,
        private SiteRegistry $apps,
    ) {
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): RawHtml
    {
        Notice::assertInstanceOf($node);

        /** @var Notice $node */
        $content = $childRenderer->renderNodes($node->children());

        /** @var array<string, string> $attributes from an `{#anchor .class}` line above the notice */
        $attributes = $node->data->get('attributes');

        return new RawHtml($this->twig->render(
            $this->apps->get()->getView('/component/notice.html.twig', '@Pushword'),
            [
                'level' => $node->level,
                'title' => $node->title,
                'content' => $content,
                'id' => $attributes['id'] ?? '',
                'class' => $attributes['class'] ?? '',
            ]
        ));
    }
}
