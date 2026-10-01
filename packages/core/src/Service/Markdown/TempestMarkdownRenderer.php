<?php

declare(strict_types=1);

namespace Pushword\Core\Service\Markdown;

use Pushword\Core\Service\LinkProvider;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Twig\MediaExtension;
use Tempest\Markdown\Markdown;
use Tempest\Markdown\Rules\HeadingRule;
use Twig\Environment as Twig;

/** Routes standalone syntax, block composition and Tempest parsing in precedence order. */
final readonly class TempestMarkdownRenderer
{
    /** Reject unsupported syntax before generic Tempest parsing. */
    private const array UNSUPPORTED_PATTERNS = [
        '/\A<(?:input|hr|br|img)\b/i',
        '/^ {0,3}(?:[-*+]|\d+[.)]) \[[xX ]\] /m',
        '/\{#[^}\n]*[^\x00-\x7F][^}\n]*\}/',
        '/^#{1,6} [^\n]+\n\{[.#][^}\n]+\}$/m',
        '/(?!\A)\{(?:[.#]|[a-z][a-z0-9_-]*=)[^}\n]*\s+[^}\n]*\}/i',
        // An attribute line followed by a line opening with `{`: CommonMark may apply both to the block.
        '/^\{[^{}\n]+\}\n\{/m',
    ];

    private TempestStandaloneRenderer $standalone;

    private TempestBlockRenderer $blocks;

    public function __construct(?LinkProvider $linkProvider = null, ?SiteRegistry $apps = null, ?Twig $twig = null, ?MediaExtension $mediaExtension = null)
    {
        $markdown = new Markdown(null);
        $markdown->removeRules(HeadingRule::class)->prependRules(new HeadingWithoutIdRule());
        $images = new TempestImageRestorer($mediaExtension, $apps);
        $parsed = new TempestParsedMarkdownRenderer($markdown, $linkProvider, $apps, $images, $this->render(...), $this->renderInline(...));
        $notices = new TempestNoticeRenderer($twig, $apps, $this->render(...));
        $this->standalone = new TempestStandaloneRenderer($linkProvider, $apps, $notices, $this->render(...), $this->renderInline(...));
        $this->blocks = new TempestBlockRenderer($markdown, $apps, $mediaExtension, $images, $parsed, $this->render(...));
    }

    public function render(string $source): ?string
    {
        if ('' === $source) {
            return '';
        }

        if (1 === preg_match('/\A\{(?:[.#]|[a-z][a-z0-9_-]*=)[^{}\r\n]+\}[ \t]+\S/i', $source)) {
            return $this->renderSameLineAttribute($source);
        }

        $standalone = $this->standalone->tryRender($source);
        if (false !== $standalone) {
            return $standalone;
        }

        $codeSpans = $this->extractCodeSpans($source);
        if (null === $codeSpans) {
            return null;
        }

        [$source, $literalCode] = $codeSpans;

        // CommonMark accepts several attributes in a `{…}` block after a link.
        $sourceWithoutLinkAttributes = preg_replace('/(?<!!)(\[[^\[\]\n]*\]\((?:<[^<>\n]*>|[^()\s]*)\))\{[^{}\n]*\}/', '$1', $source) ?? $source;
        foreach (self::UNSUPPORTED_PATTERNS as $pattern) {
            if (1 === preg_match($pattern, $sourceWithoutLinkAttributes)) {
                return null;
            }
        }

        $html = $this->blocks->render($source);
        if (null === $html) {
            return null;
        }

        foreach ($literalCode as $index => $code) {
            $html = str_replace("\u{E064}".$index."\u{E065}", $code, $html);
        }

        return $html;
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

    /**
     * Sets code spans aside the way CommonMark reads them: a backtick run closes on the next run of
     * the same length, so backticks of another length are code text. Fenced code stays as written.
     * Declines what one line cannot settle: an unclosed run (a literal backtick, or a span going on
     * to the next line), an escaped backtick, a span that may sit in an HTML tag, an autolink, a link
     * destination or a table cell, a span carrying `{…}` attributes, and backticks in what may be an
     * indented code block.
     *
     * @return ?array{string, list<string>}
     */
    private function extractCodeSpans(string $source): ?array
    {
        if (! str_contains($source, '`')) {
            return [$source, []];
        }

        $literalCode = [];
        $fence = null;
        $lines = explode("\n", $source);
        foreach ($lines as $index => $line) {
            if (null !== $fence) {
                if (1 === preg_match('/^ {0,3}'.$fence[0].'{'.\strlen($fence).',}[ \t]*\r?$/D', $line)) {
                    $fence = null;
                }

                continue;
            }

            if (1 === preg_match('/^ {0,3}(`{3,}(?=[^`]*$)|~{3,})/D', $line, $opening)) {
                $fence = $opening[1];

                continue;
            }

            if (! str_contains($line, '`')) {
                continue;
            }

            if (1 === preg_match('/^(?: {4}| {0,3}\t)/', $line)) {
                return null;
            }

            $textOutsideCode = '';
            $rendered = '';
            $offset = 0;
            while (1 === preg_match('/`+/', $line, $run, \PREG_OFFSET_CAPTURE, $offset)) {
                [$ticks, $start] = $run[0];
                $before = substr($line, $offset, $start - $offset);
                $textOutsideCode .= $before;
                if (str_ends_with($textOutsideCode, '\\') || 1 === preg_match('/<[^>]*$|\]\([^)]*$/D', $textOutsideCode)) {
                    return null;
                }

                $codeStart = $start + \strlen($ticks);
                if (1 !== preg_match('/(?<!`)'.$ticks.'(?!`)/', $line, $closing, \PREG_OFFSET_CAPTURE, $codeStart)) {
                    return null;
                }

                $codeEnd = $closing[0][1];
                $code = substr($line, $codeStart, $codeEnd - $codeStart);
                $offset = $codeEnd + \strlen($ticks);
                if (1 === preg_match('/^\{[^}\n]+\}/', substr($line, $offset))) {
                    return null;
                }

                if (str_contains($code, '|') && substr_count($line, '|') > substr_count($code, '|')) {
                    return null;
                }

                if (str_starts_with($code, ' ') && str_ends_with($code, ' ') && '' !== trim($code, ' ')) {
                    $code = substr($code, 1, -1);
                }

                $literalCode[] = '<code>'.htmlspecialchars($code, \ENT_NOQUOTES | \ENT_SUBSTITUTE).'</code>';
                $rendered .= $before."\u{E064}".(\count($literalCode) - 1)."\u{E065}";
            }

            $lines[$index] = $rendered.substr($line, $offset);
        }

        return [implode("\n", $lines), $literalCode];
    }

    /**
     * Renders a paragraph opened by a single `{.class}` or `{#id}`; anything else is left to CommonMark.
     * The class must start like a CommonMark class name, `-?[_a-zA-Z]`: CommonMark keeps any other marker as text.
     */
    private function renderSameLineAttribute(string $source): ?string
    {
        if (1 !== preg_match('/\A\{(?<type>\.(?=-?[_a-zA-Z])|#|id=)(?<value>[\p{L}\p{N}_-]+)\}[ \t]+(?<text>\S[\s\S]*)\z/Du', $source, $attribute)) {
            return null;
        }

        // CommonMark would also apply to the paragraph any other attribute opening a line or following a space.
        if (1 === preg_match('/(?:^|[ \t])\{:?(?:[.#]|[a-z_:][\w.:-]*=)[^{}\n]*\}/im', $attribute['text'])) {
            return null;
        }

        $html = $this->render($attribute['text']);
        if (null === $html || ! str_starts_with($html, '<p>')) {
            return null;
        }

        $name = '.' === $attribute['type'] ? 'class' : 'id';

        return '<p '.$name.'="'.htmlspecialchars($attribute['value'], \ENT_QUOTES | \ENT_SUBSTITUTE).'">'.substr($html, 3);
    }
}
