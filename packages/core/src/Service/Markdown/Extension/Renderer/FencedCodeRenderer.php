<?php

namespace Pushword\Core\Service\Markdown\Extension\Renderer;

use InvalidArgumentException;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Util\Xml;
use Pushword\Core\Site\SiteConfig;
use Pushword\Core\Site\SiteRegistry;
use Stringable;

/**
 * Same output as League's default FencedCodeRenderer, but tags the <pre> with
 * a site-configured CSS class (e.g. "microlight" or "hljs") so a highlighter
 * theme picks it up. Configured per-site via the `fenced_code_pre_class`
 * custom property in the app config; a site declaring none gets League's output
 * byte for byte, which is why this can be registered unconditionally.
 *
 * The class is read per render, never captured when the renderer is built: the
 * markdown Environment is built once per process and one process serves several
 * hosts (pw:static, worker mode), so a captured value would freeze the first
 * host's class onto every other site's code blocks.
 */
final readonly class FencedCodeRenderer implements NodeRendererInterface
{
    public function __construct(private SiteRegistry $apps)
    {
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): Stringable
    {
        if (! $node instanceof FencedCode) {
            throw new InvalidArgumentException('Expected '.FencedCode::class.', got '.$node::class);
        }

        $attrs = $node->data->getData('attributes');

        $infoWords = $node->getInfoWords();
        if ([] !== $infoWords && '' !== $infoWords[0]) {
            $class = $infoWords[0];
            if (! str_starts_with($class, 'language-')) {
                $class = 'language-'.$class;
            }

            $attrs->append('class', $class);
        }

        /** @var array<string, array<string>|bool|string> $codeAttrs */
        $codeAttrs = $attrs->export();

        $preClass = $this->apps->get()->getStr(SiteConfig::FENCED_CODE_PRE_CLASS);

        return new HtmlElement(
            'pre',
            '' !== $preClass ? ['class' => $preClass] : [],
            new HtmlElement('code', $codeAttrs, Xml::escape($node->getLiteral()))
        );
    }
}
