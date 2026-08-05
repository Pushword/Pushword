<?php

namespace Pushword\Core\Service\Markdown\Extension;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;
use Pushword\Core\Service\Markdown\Extension\Node\Notice;
use Pushword\Core\Service\Markdown\Extension\Parser\NoticeStartParser;
use Pushword\Core\Service\Markdown\Extension\Renderer\NoticeRenderer;
use Pushword\Core\Site\SiteRegistry;
use Twig\Environment as Twig;

/**
 * Notices — a blockquote opened by `> [!label]`, rendered through the
 * `component/notice.html.twig` component.
 *
 * Block-level only: kept out of PushwordExtension so the `markdown_inline`
 * converter, which promises never to parse block syntax, stays untouched.
 */
final readonly class NoticeExtension implements ExtensionInterface
{
    public function __construct(
        private Twig $twig,
        private SiteRegistry $apps,
    ) {
    }

    public function register(EnvironmentBuilderInterface $environment): void
    {
        // Above CommonMark's blockquote parser (70), which would otherwise
        // claim the line first and leave the marker as literal text.
        $environment->addBlockStartParser(new NoticeStartParser(), 80);

        $environment->addRenderer(Notice::class, new NoticeRenderer($this->twig, $this->apps));
    }
}
