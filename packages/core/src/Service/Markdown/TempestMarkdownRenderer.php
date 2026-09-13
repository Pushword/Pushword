<?php

declare(strict_types=1);

namespace Pushword\Core\Service\Markdown;

use Pushword\Core\Service\LinkProvider;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Twig\MediaExtension;
use Tempest\Markdown\Markdown;
use Twig\Environment as Twig;

/** Routes standalone syntax, block composition and Tempest parsing in precedence order. */
final readonly class TempestMarkdownRenderer
{
    /** Reject unsupported syntax before generic Tempest parsing. */
    private const array UNSUPPORTED_PATTERNS = [
        '/\A<(?:input|hr|br|img)\b/i',
        '/^ {0,3}(?:[-*+]|\d+[.)]) \[[xX ]\] /m',
        '/`[^`\n]+`\{[^}\n]+\}/',
        '/\{#[^}\n]*[^\x00-\x7F][^}\n]*\}/',
        '/^#{1,6} [^\n]+\n\{[.#][^}\n]+\}$/m',
        '/(?!\A)\{(?:[.#]|[a-z][a-z0-9_-]*=)[^}\n]*\s+[^}\n]*\}/i',
    ];

    private TempestStandaloneRenderer $standalone;

    private TempestBlockRenderer $blocks;

    public function __construct(?LinkProvider $linkProvider = null, ?SiteRegistry $apps = null, ?Twig $twig = null, ?MediaExtension $mediaExtension = null)
    {
        $markdown = new Markdown(null);
        $images = new TempestImageRestorer($mediaExtension, $apps);
        $parsed = new TempestParsedMarkdownRenderer($markdown, $linkProvider, $apps, $images, $this->render(...));
        $notices = new TempestNoticeRenderer($twig, $apps, $this->render(...));
        $this->standalone = new TempestStandaloneRenderer($linkProvider, $apps, $notices, $this->render(...), $this->renderInline(...));
        $this->blocks = new TempestBlockRenderer($markdown, $apps, $mediaExtension, $images, $parsed, $this->render(...));
    }

    public function render(string $source): ?string
    {
        if ('' === $source) {
            return '';
        }

        $standalone = $this->standalone->tryRender($source);
        if (false !== $standalone) {
            return $standalone;
        }

        foreach (self::UNSUPPORTED_PATTERNS as $pattern) {
            if (1 === preg_match($pattern, $source)) {
                return null;
            }
        }

        return $this->blocks->render($source);
    }

    public function renderInline(string $source): ?string
    {
        $source = preg_replace('/[ \t]*\r?\n[ \t]*/', ' ', trim($source));
        if (null === $source) {
            return null;
        }

        if ('' === $source) {
            return '';
        }

        $literalPrefix = '';
        if (1 === preg_match('/^((?:#{1,6}|[-+*]|\d+[.)]|>)\s+)/', $source, $marker)) {
            $literalPrefix = $marker[1];
            $source = substr($source, \strlen($literalPrefix));
        }

        $html = $this->render($source);

        if (null === $html || ! str_starts_with($html, '<p>') || ! str_ends_with($html, "</p>\n")) {
            return null;
        }

        return htmlspecialchars($literalPrefix, \ENT_QUOTES | \ENT_SUBSTITUTE).substr($html, 3, -5);
    }
}
