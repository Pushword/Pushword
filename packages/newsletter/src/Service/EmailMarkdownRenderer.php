<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Service;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\Autolink\EmailAutolinkParser;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Renderer\HtmlRenderer;
use Pushword\Core\Entity\Media;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Twig\MediaExtension;

/** Email content must not depend on the web renderer's JavaScript or media markup. */
final readonly class EmailMarkdownRenderer
{
    private MarkdownParser $parser;

    private HtmlRenderer $renderer;

    public function __construct(
        private MediaExtension $mediaExtension,
        private SiteRegistry $sites,
    ) {
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new AttributesExtension());
        $environment->addExtension(new StrikethroughExtension());
        $environment->addExtension(new TableExtension());
        $environment->addExtension(new TaskListExtension());
        $environment->addInlineParser(new EmailAutolinkParser());

        $this->parser = new MarkdownParser($environment);
        $this->renderer = new HtmlRenderer($environment);
    }

    public function transform(string $markdown, string $mainHost): string
    {
        $document = $this->parser->parse($markdown);
        $base = rtrim($this->sites->get($mainHost)->baseUrl, '/');
        foreach ($document->iterator() as $node) {
            if ($node instanceof Link) {
                $classes = $node->data->get('attributes.class', '');
                if (\is_string($classes) && 1 === preg_match('/(?:^|\s)link-btn(-outline)?(?:\s|$)/', $classes, $match)) {
                    $outline = isset($match[1]);
                    $node->data->set('attributes.style', 'display:inline-block;padding:12px 20px;border:1px solid #1c1c1c;border-radius:4px;font-weight:bold;text-decoration:none;'.($outline ? 'color:#1c1c1c;background-color:#fafafa;' : 'color:#fafafa;background-color:#1c1c1c;'));
                }
            }

            if (! $node instanceof Image) {
                continue;
            }

            $src = $node->getUrl();
            if ($this->mediaExtension->mayBeAnInternalImage($src)) {
                $media = $this->mediaExtension->tryTransformStringToMedia($src);
                if ($media instanceof Media) {
                    // Keep the original format for mail clients that cannot decode WebP.
                    $src = $this->mediaExtension->getImageUrl($media, 'default', pathinfo($media->getFileName(), \PATHINFO_EXTENSION));
                }
            }

            $node->setUrl(str_starts_with($src, '/') && ! str_starts_with($src, '//') ? $base.$src : $src);
            $node->data->set('attributes.width', '100%');
            $node->data->set('attributes.style', 'display:block;width:100%;max-width:100%;height:auto;');
            $node->data->remove('attributes.height');
        }

        return $this->renderer->renderDocument($document)->getContent();
    }
}
