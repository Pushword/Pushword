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
            $this->view($node->level),
            [
                'level' => $node->level,
                'title' => $node->title,
                'content' => $content,
                'id' => $attributes['id'] ?? '',
                'class' => $attributes['class'] ?? '',
                'params' => array_diff_key($attributes, ['id' => '', 'class' => '']),
            ]
        ));
    }

    /**
     * A label may own a component of its own — `> [!faq]` renders through
     * `component/notice/faq.html.twig` when the site has one — and every other
     * label falls back to the generic notice. The label charset (see
     * {@see \Pushword\Core\Service\Markdown\Extension\Parser\NoticeStartParser})
     * is `[a-z0-9_-]`, so it cannot walk out of the directory.
     */
    private function view(string $level): string
    {
        $site = $this->apps->get();
        $view = $site->getView('/component/notice/'.$level.'.html.twig', '@Pushword');

        return $this->twig->getLoader()->exists($view)
            ? $view
            : $site->getView('/component/notice.html.twig', '@Pushword');
    }
}
