<?php

declare(strict_types=1);

namespace Pushword\Core\Service\Markdown;

use Pushword\Core\Component\EntityFilter\Filter\Date;
use Pushword\Core\Service\LinkProvider;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Twig\MediaExtension;
use Tempest\Markdown\Markdown;
use Tempest\Markdown\Rules\FrontMatterRule;
use Throwable;
use Twig\Environment as Twig;

/** Uses Tempest when the rendered HTML preserves Pushword's document structure. */
final readonly class TempestMarkdownRenderer
{
    /** Syntax for which Tempest changes the parsed document, not just its serialization. */
    private const array COMMONMARK_ONLY_PATTERNS = [
        '/\A\{(?!id=)[^}\n]+\}\n/',
        '/(?<!#)\[[^\]\n]+\]\(mailto:/',
        '/\A<(?:input|hr|br|img)\b/i',
        '/\A<(?:[^<>\s]+@[^<>\s]+|tel:[^<>\s]+)>/',
        '/^ {0,3}(?:[-*+]|\d+[.)]) \[[xX ]\] /m',
        '/`[^`\n]+`\{[^}\n]+\}/',
        '/\{#[^}\n]*[^\x00-\x7F][^}\n]*\}/',
        '/\[[^\]\n]+\]\([^\n)]+\)\{#/',
        '/^#{1,6} [^\n]+\n\{[.#][^}\n]+\}$/m',
        '/\{(?:[.#]|[a-z][a-z0-9_-]*=)[^}\n]*\s+[^}\n]*\}/i',
    ];

    private Markdown $markdown;

    private Markdown $markdownWithoutFrontMatter;

    private ?Date $dateFilter;

    public function __construct(private ?LinkProvider $linkProvider = null, private ?SiteRegistry $apps = null, private ?Twig $twig = null, private ?MediaExtension $mediaExtension = null)
    {
        $this->markdown = new Markdown(null);
        $this->markdownWithoutFrontMatter = new Markdown(null)->removeRules(FrontMatterRule::class);
        $this->dateFilter = null === $apps ? null : new Date($apps);
    }

    public function render(string $source): ?string
    {
        if ('' === $source) {
            return '';
        }

        if (str_contains($source, '](<')) {
            return null;
        }

        foreach (self::COMMONMARK_ONLY_PATTERNS as $pattern) {
            if (1 === preg_match($pattern, $source)) {
                return null;
            }
        }

        if (1 === preg_match('/\n[ \t]*\n/', $source) && 1 !== preg_match('/^(?:[-*+] |[0-9]+[.)] |>|`{3}|~{3}|\{id=)/', $source)) {
            $blocks = preg_split('/\n[ \t]*\n+/', trim($source, "\n"));
            if (false === $blocks) {
                return null;
            }

            $html = '';
            foreach ($blocks as $block) {
                $part = $this->render(rtrim($block));
                if (null === $part) {
                    return null;
                }

                $html .= $part;
            }

            return $html;
        }

        $firstLine = strstr($source, "\n", true);
        if (false !== $firstLine && str_contains($source, "\n    ") && 1 !== preg_match('/^(?:[-*+] |[0-9]+[.)] |>)/', $source) && (str_ends_with($firstLine, '  ') || str_starts_with($firstLine, '|'))) {
            $source = preg_replace('/(?m)^ {4}(?=\S)/', '', $source) ?? $source;
        }

        if (1 !== preg_match('/^(?:[-*+] |[0-9]+[.)] |>|\{id=)/', $source) && 1 === preg_match('/\A(.+?)\n((?:- |1[.)] )[^\n]+[\s\S]*)\z/sD', $source, $mixedBlocks)) {
            $before = $this->render(rtrim($mixedBlocks[1]));
            $after = $this->render($mixedBlocks[2]);

            if (null !== $before && null !== $after) {
                return $before.$after;
            }
        }

        if (1 === preg_match('/^(#{1,6}) ([\p{L}\p{N} -]+)$/Du', $source, $plainHeading)) {
            $level = \strlen($plainHeading[1]);

            return '<h'.$level.'>'.htmlspecialchars(trim($plainHeading[2]), \ENT_QUOTES | \ENT_SUBSTITUTE).'</h'.$level.">\n";
        }

        if (1 === preg_match('/^(#{1,6}) (<span\b[^>]*>[^<]*<\/span>)$/D', $source, $spanHeading)) {
            $level = \strlen($spanHeading[1]);

            return '<h'.$level.'>'.$spanHeading[2].'</h'.$level.">\n";
        }

        if (1 === preg_match('/\A[^\r\n]+\r?\n {0,3}(?:=+|-+)[ \t]*\z/D', $source)) {
            $html = $this->markdown->parse($source)->html;
            $html = preg_replace('/^(<h[12]) id="[^"]*"/', '$1', $html, 1, $replacements);

            return null !== $html && 1 === $replacements ? $html."\n" : null;
        }

        if (1 === preg_match('/^(?:\*[ \t]*){3,}$/D', $source)) {
            return "<hr />\n";
        }

        if (1 === preg_match('/\A(\{\.![^{}\n]+\})(?:\n(.+))?\z/sD', $source, $block)) {
            $literal = rtrim($this->markdown->parse($block[1])->html)."\n";
            $following = isset($block[2]) ? $this->render($block[2]) : '';

            return null === $following ? null : $literal.$following;
        }

        if (1 === preg_match('/\A\{id=([A-Za-z0-9_-]+)\}\n(.+)\z/sD', $source, $block)) {
            $html = $this->render($block[2]);
            if (null === $html) {
                return null;
            }

            $html = preg_replace('/^<(h[1-6]|p|ul|ol)>/', '<$1 id="'.$block[1].'">', $html, 1, $count);

            return 1 === $count ? $html : null;
        }

        if (1 === preg_match('/^(\[![A-Za-z0-9_-]+\] [^\n{}]+) \{id=([A-Za-z0-9_-]+)\}$/D', $source, $label)) {
            $html = $this->render($label[1]);

            return null === $html ? null : str_replace('<p>', '<p id="'.$label[2].'">', $html);
        }

        $literalNoticeLabel = 1 === preg_match('/^\[![A-Za-z0-9_-]+\] [^\n{}]+$/D', $source);
        if ($literalNoticeLabel) {
            $source = str_replace(['[', ']'], ["\u{E010}", "\u{E011}"], $source);
        }

        if (str_starts_with($source, '![') && 1 === preg_match('/^!\[([^\]\n]*)\]\(([^()\n]+)\)$/D', $source, $image)) {
            if (str_contains($image[2], ' ')) {
                return '<p>'.htmlspecialchars($source, \ENT_NOQUOTES | \ENT_SUBSTITUTE)."</p>\n";
            }

            if (null === $this->mediaExtension || null === $this->apps || ! str_contains($this->markdown->parse($source)->html, '<img ')) {
                return null;
            }

            try {
                $alt = preg_replace('/_-_(.*?)_-_/', '_-$1-_', $image[1]) ?? $image[1];
                $html = $this->mediaExtension->renderImage($image[2], htmlspecialchars($alt), link: true, sizes: $this->apps->get()->bodyImageSizes());
            } catch (Throwable) {
                $html = BrokenImageComment::for($image[2]);
            }

            return '<p>'.$html."</p>\n";
        }

        if (1 === preg_match('/\A\* ([^\n]+)\n\* (!\[[^\]\n]*\]\([^()\s\n]+\))\z/D', $source, $imageList)) {
            $first = $this->render($imageList[1]);
            $second = $this->render($imageList[2]);
            if (null !== $first && null !== $second && str_starts_with($first, '<p>') && str_starts_with($second, '<p>')) {
                return "<ul>\n<li>".substr($first, 3, -5)."</li>\n<li>".substr($second, 3, -5)."</li>\n</ul>\n";
            }
        }

        $inlineImages = [];
        if (str_contains($source, '![')) {
            if (null === $this->mediaExtension || null === $this->apps) {
                return null;
            }

            $source = preg_replace_callback('/\[!\[([^\]\n*]*)\]\(((?:[^()\s\n]|\([^()\n]*\))+?)\)\]\(([^()\s\n]+)(?: "([^"\n]*)")?\)/', static function (array $match) use (&$inlineImages): string {
                $marker = "\u{E034}".\count($inlineImages)."\u{E035}";
                $inlineImages[] = ['src' => $match[2], 'alt' => $match[1], 'linked' => true];

                return '['.$marker.']('.$match[3].(isset($match[4]) ? ' "'.$match[4].'"' : '').')';
            }, $source);
            if (null === $source) {
                return null;
            }

            $source = preg_replace_callback('/!\[([^\]\n*]*)\]\(((?:[^()\s\n]|\([^()\n]*\))*?)\)/', static function (array $match) use (&$inlineImages): string {
                $marker = "\u{E034}".\count($inlineImages)."\u{E035}";
                $inlineImages[] = ['src' => $match[2], 'alt' => $match[1], 'linked' => false];

                return $marker;
            }, $source);
            if (null === $source) {
                return null;
            }

            if (str_contains($source, '![') && 1 === preg_match('/!\[[^\]\n]*\]\([^\n)]*$/D', $source)) {
                $source = str_replace('![', "\u{E044}[", $source);
            }

            if (str_contains($source, '![')) {
                return null;
            }
        }

        // Ambiguous delimiter runs do not have the same binding in Tempest and CommonMark.
        if (1 === preg_match('/(?<=\d)__(?=\p{L})|"__/', $source)
            || 1 === preg_match('/\*\*\(\*\*(?!\[)/', $source)
            || str_contains($source, '\\"')
            || (str_contains($source, '\\*\\*') && 1 === preg_match('/\*\*,[^*\n]{0,40}\*\*/', $source))
            || 1 === preg_match('/[\p{L}\p{N}]_[.!?] {2,}\n(?!_)[^\n]*_/u', $source)
            || 1 === preg_match('/[\p{L}\p{N}]_\x27[^\s_]+_|_\p{Lu}_\p{L}|\b[A-Z]_[a-z]|_\x{200B}_|(?<![\p{L}\p{N}])_[^_\n]+\(_/u', $source)
            || 1 === preg_match('/^_[^_*\s\n]+\*\*|`<[^`]+>`|^\* .*_[0-9][^_\n]*_[\p{L}]/mu', $source)
        ) {
            return null;
        }

        if (1 === preg_match('/^<!--[\s\S]*-->$/D', $source)) {
            return rtrim($source)."\n";
        }

        if (1 === preg_match('/\A<h([1-6])\b[^>]*>[\s\S]*<\/h\1>(?:\n<div\b[^\n]*>)?\z/D', $source)) {
            return rtrim($source)."\n";
        }

        if (1 === preg_match('/\A(.+)\n((?:<h[1-6]\b|<!--)[\s\S]+)\z/sD', $source, $blocks)) {
            $before = $this->render($blocks[1]);
            $after = $this->render($blocks[2]);

            if (null !== $before && null !== $after) {
                return $before.$after;
            }
        }

        if (1 === preg_match('/\A(.+)\n(<\/?div\b[^\n]*>)\z/sD', $source, $blocks)) {
            $content = $this->render($blocks[1]);

            return null === $content ? null : $content.$blocks[2]."\n";
        }

        if (1 === preg_match('/\A([^\n|]+)\n(\|[^\n]+\|\n\|[\s|:-]+\|\n[\s\S]+)\z/D', $source, $blocks)) {
            $before = $this->render($blocks[1]);
            $table = $this->render($blocks[2]);

            return null === $before || null === $table ? null : $before.$table;
        }

        if (1 === preg_match('/\A(<\/?div\b[^\n]*>)\n(.+)\z/sD', $source, $blocks)) {
            $content = $this->render($blocks[2]);

            return null === $content ? null : $blocks[1]."\n".$content;
        }

        if (str_starts_with($source, '> [!')) {
            return $this->renderNotice($source);
        }

        if (str_starts_with($source, '> ')) {
            $lines = explode("\n", rtrim($source, "\n"));
            foreach ($lines as $index => $line) {
                if ('>' === $line) {
                    $lines[$index] = '';
                } elseif (str_starts_with($line, '> ')) {
                    $lines[$index] = substr($line, 2);
                } elseif (str_starts_with($line, '>,')) {
                    $lines[$index] = substr($line, 1);
                } else {
                    return null;
                }
            }

            $content = $this->render(implode("\n", $lines));

            return null === $content ? null : "<blockquote>\n".$content."</blockquote>\n";
        }

        if (! str_starts_with($source, '- ') && 1 === preg_match('/\A(.+?)\n((?:- [^\n]+(?:\n|$))+?)\z/sD', $source, $blocks)) {
            $intro = $this->render(rtrim($blocks[1]));
            $list = $this->render($blocks[2]);

            return null === $intro || null === $list ? null : $intro.$list;
        }

        if (1 === preg_match('/\A((?:- [^\n]+\n)+)(#{1,6} [^\n]+)\z/D', $source, $blocks)) {
            $list = $this->render($blocks[1]);
            $heading = $this->render($blocks[2]);

            return null === $list || null === $heading ? null : $list.$heading;
        }

        if (1 === preg_match('/\A(#{1,6} [^\n]+)\n(.+)\z/sD', $source, $blocks)) {
            $heading = $this->render($blocks[1]);
            $content = $this->render($blocks[2]);

            return null === $heading || null === $content ? null : $heading.$content;
        }

        if (! str_starts_with($source, '{') && 1 === preg_match('/\A([^\n]+)\n(?:[ \t]*\n)?(#{1,6} [^\n]+)\z/D', $source, $blocks)) {
            $before = $this->render($blocks[1]);
            $after = $this->render($blocks[2]);

            return null === $before || null === $after ? null : $before.$after;
        }

        if (1 === preg_match('/(?m)^([0-9]{1,9})\) /', $source, $firstItem, \PREG_OFFSET_CAPTURE)) {
            if (1 === preg_match('/(?m)^[0-9]{1,9}\)\S/', $source, $malformed, \PREG_OFFSET_CAPTURE)
                && $malformed[0][1] < $firstItem[0][1]) {
                return null;
            }

            if (0 !== $firstItem[0][1] && '1' !== $firstItem[1][0]) {
                $literal = preg_replace('/(?m)^([2-9][0-9]*)\) /', '$1'."\u{E045}".' ', $source);
                $html = null === $literal ? null : $this->render($literal);

                return null === $html ? null : str_replace("\u{E045}", ')', $html);
            }

            $lines = explode("\n", rtrim($source, "\n"));
            $intro = [];
            $items = [];
            $start = null;
            foreach ($lines as $line) {
                if (1 === preg_match('/^([0-9]{1,9})\) (.+)$/D', $line, $item)) {
                    $start ??= (int) $item[1];
                    $items[] = $item[2];
                } elseif ([] === $items) {
                    $intro[] = $line;
                } elseif ('' !== trim($line) && 1 !== preg_match('/^(?:#{1,6}[ \t]|>|`{3}|~{3})/', $line)) {
                    $items[array_key_last($items)] .= "\n".ltrim($line);
                } else {
                    return null;
                }
            }

            $html = '';
            if ([] !== $intro) {
                $html = $this->render(rtrim(implode("\n", $intro)));
                if (null === $html) {
                    return null;
                }
            }

            $html .= 1 === $start ? "<ol>\n" : '<ol start="'.$start.'">'."\n";
            foreach ($items as $item) {
                $content = $this->render(rtrim($item));
                if (null === $content || ! str_starts_with($content, '<p>') || ! str_ends_with($content, "</p>\n")) {
                    return null;
                }

                $html .= '<li>'.substr($content, 3, -5)."</li>\n";
            }

            return $html."</ol>\n";
        }

        if (1 === preg_match('/(?m)^ {2,}[-*] +/', $source)) {
            $items = [];
            $loose = false;
            $trailingRule = false;
            foreach (explode("\n", rtrim($source, "\n")) as $line) {
                if ('' === trim($line)) {
                    $loose = true;
                } elseif (1 === preg_match('/^ {4}\* \* \*$/D', $line)) {
                    $trailingRule = true;
                } elseif ($trailingRule) {
                    return null;
                } elseif (1 === preg_match('/^( *)(?:[-*]) +(.+)$/D', $line, $match)) {
                    $items[] = ['indent' => \strlen($match[1]), 'text' => $match[2]];
                } elseif ([] !== $items && 1 === preg_match('/^ {4,}\S/', $line)) {
                    $last = array_pop($items);
                    $items[] = ['indent' => $last['indent'], 'text' => $last['text']."\n".ltrim($line)];
                } else {
                    return null;
                }
            }

            if (0 !== $items[0]['indent']) {
                return null;
            }

            $position = 0;
            $html = $this->renderNestedList($items, $position, 0, $loose);
            if ($trailingRule && null !== $html && str_ends_with($html, "</li>\n</ul>\n")) {
                $html = substr($html, 0, -\strlen("</li>\n</ul>\n"))."<hr />\n</li>\n</ul>\n";
            }

            return \count($items) === $position ? $html : null;
        }

        if (1 === preg_match('/^[0-9]+\. /', $source) && (str_contains($source, "\n   ") || 1 === preg_match('/\n(?![0-9]+\. )\S/', $source))) {
            $items = [];
            $start = null;
            foreach (explode("\n", rtrim($source, "\n")) as $line) {
                if (1 === preg_match('/^([0-9]+)\. (.+)$/D', $line, $match)) {
                    $start ??= (int) $match[1];
                    $items[] = $match[2];
                } elseif ([] !== $items && (str_starts_with($line, '   ') || $line === ltrim($line))) {
                    $items[array_key_last($items)] .= "\n".ltrim($line);
                } else {
                    return null;
                }
            }

            $html = 1 === $start ? "<ol>\n" : '<ol start="'.$start.'">'."\n";
            foreach ($items as $item) {
                $content = $this->render($item);
                if (null === $content || ! str_starts_with($content, '<p>') || ! str_ends_with($content, "</p>\n")) {
                    return null;
                }

                $html .= '<li>'.substr($content, 3, -5)."</li>\n";
            }

            return $html."</ol>\n";
        }

        if (str_starts_with($source, '- ') && 1 === preg_match('/(?m)^- [0-9]+\. /', $source)) {
            $html = "<ul>\n";
            foreach (explode("\n", rtrim($source, "\n")) as $line) {
                if (! str_starts_with($line, '- ')) {
                    return null;
                }

                $item = substr($line, 2);
                $content = $this->render($item);
                if (null === $content) {
                    return null;
                }

                if (str_starts_with($content, '<ol')) {
                    $html .= "<li>\n".$content."</li>\n";
                } elseif (str_starts_with($content, '<p>') && str_ends_with($content, "</p>\n")) {
                    $html .= '<li>'.substr($content, 3, -5)."</li>\n";
                } else {
                    return null;
                }
            }

            return $html."</ul>\n";
        }

        if (1 === preg_match('/^\* \|[^\n]+\n {4}\|/', $source)) {
            $table = preg_replace('/(?m)^ {4}(?=\|)/', '', substr($source, 2));
            $html = null === $table ? null : $this->render($table);

            return null !== $html && str_starts_with($html, '<table>') ? "<ul>\n<li>\n".$html."</li>\n</ul>\n" : null;
        }

        if (str_starts_with($source, '* |') && str_contains($source, "\n|")) {
            $content = $this->render(str_replace('|', "\u{E049}", substr($source, 2)));
            if (null === $content || ! str_starts_with($content, '<p>') || ! str_ends_with($content, "</p>\n")) {
                return null;
            }

            return "<ul>\n<li>".str_replace("\u{E049}", '|', substr($content, 3, -5))."</li>\n</ul>\n";
        }

        if (1 === preg_match('/(?m)^\* \* /', $source)) {
            $html = "<ul>\n";
            foreach (explode("\n", rtrim($source, "\n")) as $line) {
                if (! str_starts_with($line, '* ')) {
                    return null;
                }

                $content = $this->render(substr($line, 2));
                if (null === $content) {
                    return null;
                }

                if (str_starts_with($line, '* * ') && str_starts_with($content, '<ul>')) {
                    $html .= "<li>\n".$content."</li>\n";
                } elseif (str_starts_with($content, '<p>') && str_ends_with($content, "</p>\n")) {
                    $html .= '<li>'.substr($content, 3, -5)."</li>\n";
                } else {
                    return null;
                }
            }

            return $html."</ul>\n";
        }

        if (str_starts_with($source, '* ') || str_starts_with($source, '- ')) {
            $source = preg_replace('/(?m)^([*-]) {2,3}(?=\S)/', '$1 ', $source);
            if (null === $source) {
                return null;
            }
        }

        if ((str_starts_with($source, '* ') || str_starts_with($source, '- ')) && (str_starts_with($source, '* ') || str_contains($source, "\n  ") || 1 === preg_match('/\n(?!- )\S/', $source))) {
            if ([] !== $inlineImages) {
                return null;
            }

            $items = [];
            $codeBlocks = [];
            $loose = false;
            $blank = false;
            $codeBlankLines = 0;
            foreach (explode("\n", rtrim($source, "\n")) as $line) {
                if ('' === trim($line)) {
                    $blank = true;
                    if ([] !== $items && isset($codeBlocks[array_key_last($items)])) {
                        ++$codeBlankLines;
                    }

                    continue;
                }

                $itemStart = '*' === $line || '-' === $line || str_starts_with($line, '* ') || str_starts_with($line, '- ');
                if ($blank) {
                    if (! $itemStart && ([] === $items || 1 !== preg_match('/^ {6,}\S/', $line))) {
                        return null;
                    }

                    $loose = true;
                    $blank = false;
                }

                if (! $itemStart && [] !== $items && 1 === preg_match('/^ {6,}\S/', $line)) {
                    $itemIndex = array_key_last($items);
                    $codeBlocks[$itemIndex] = ($codeBlocks[$itemIndex] ?? '').str_repeat("\n", $codeBlankLines).substr($line, 6)."\n";
                    $codeBlankLines = 0;
                } elseif ('*' === $line || '-' === $line) {
                    $items[] = '';
                    $codeBlankLines = 0;
                } elseif (str_starts_with($line, '* ') || str_starts_with($line, '- ')) {
                    $items[] = substr($line, 2);
                    $codeBlankLines = 0;
                } elseif ([] !== $items && 1 === preg_match('/^ {2,4}(\S.*)$/D', $line, $continuation)) {
                    $items[array_key_last($items)] .= "\n".$continuation[1];
                } elseif ([] !== $items && $line === ltrim($line) && 1 !== preg_match('/^(?:#{1,6}[ \t]|[0-9]+[.)] |>|`{3}|~{3})/', $line)) {
                    $items[array_key_last($items)] .= "\n".$line;
                } else {
                    return null;
                }
            }

            $html = "<ul>\n";
            foreach ($items as $index => $item) {
                if ('' === $item) {
                    $html .= "<li></li>\n";

                    continue;
                }

                $content = $this->render(rtrim($item));
                if (null === $content || ! str_starts_with($content, '<p>') || ! str_ends_with($content, "</p>\n")) {
                    return null;
                }

                $code = isset($codeBlocks[$index]) ? '<pre><code>'.htmlspecialchars($codeBlocks[$index], \ENT_NOQUOTES | \ENT_SUBSTITUTE)."</code></pre>\n" : '';
                $html .= $loose ? "<li>\n".$content.$code."</li>\n" : '<li>'.substr($content, 3, -5)."</li>\n";
            }

            return $html."</ul>\n";
        }

        if (1 === preg_match('/^-{3,}\n?$/D', $source)) {
            return rtrim($this->markdownWithoutFrontMatter->parse($source)->html)."\n";
        }

        $source = preg_replace('/(?m)^([0-9]{1,9})\. {2,3}(?=\S)/', '$1. ', $source);
        if (null === $source) {
            return null;
        }

        $listStart = 1 === preg_match('/^([0-9]{1,9})\. /', $source, $listMatches) ? (int) $listMatches[1] : null;
        $listTag = match (true) {
            str_starts_with($source, '- ') => 'ul',
            null !== $listStart => 'ol',
            default => null,
        };
        if (null !== $listTag) {
            $source = implode("\n", array_map(rtrim(...), explode("\n", $source)));
            if ('ul' === $listTag) {
                $source = preg_replace('/(?m)^- {2,3}(?=\S)/', '- ', $source);
                if (null === $source) {
                    return null;
                }
            }
        }

        $literalLeadingHash = 1 === preg_match('/^#+[^#\s\[]/', $source);
        if ($literalLeadingHash) {
            $source = "\u{E012}".substr($source, 1);
        }

        $itemClasses = [];
        if (null !== $listTag && str_contains($source, '{.')) {
            $source = preg_replace_callback('/(?m)^(- |[0-9]{1,9}\. )(?:\{\.([\p{L}\p{N}_-]+)\} )?/u', static function (array $match) use (&$itemClasses): string {
                $itemClasses[] = $match[2] ?? null;

                return $match[1];
            }, $source);
            if (null === $source) {
                return null;
            }
        }

        if (1 === preg_match('/^\|[^\n]+\|\n\|[\s|:-]+\|\n/', $source) && ! str_ends_with(rtrim($source), '|')) {
            $source = rtrim($source).'|';
        }

        $table = 1 === preg_match('/^\|[^\n]+\|\n\|([\s|:-]+)\|\n(?:\|[^\n]+\|[ \t]*\n?)+$/D', $source, $tableMatches)
            && ! str_contains($tableMatches[1], ':')
            && ! str_contains($source, '{');
        if ($table) {
            $source = preg_replace('/[ \t]+$/m', '', $source);
            if (null === $source) {
                return null;
            }
        }

        $emptyTableHeader = $table && 1 === preg_match('/^\|(?:[ \t]*(?:->)?[ \t]*\|)+\n/', $source);
        $attribute = null;
        if (null === $listTag && 1 === preg_match('/^\{(?:id=([A-Za-z0-9_-]+)(?: \.([\p{L}\p{N}_-]+))?|\.([\p{L}\p{N}_-]+))\}\n([^\r\n]+)$/Du', $source, $attributes)) {
            $class = $attributes[2] ?: $attributes[3];
            $attribute = '' !== $class ? 'class="'.$class.'"' : '';
            if ('' !== $attributes[1]) {
                $attribute .= ('' !== $attribute ? ' ' : '').'id="'.$attributes[1].'"';
            }

            $source = $attributes[4];
        } elseif (null === $listTag && 1 === preg_match('/^([^\r\n]+) \{\.([A-Za-z0-9_-]+)\}$/D', $source, $attributes)) {
            $attribute = 'class="'.$attributes[2].'"';
            $source = $attributes[1];
        }

        if (str_contains($source, 'date(')) {
            if (null === $this->dateFilter || str_contains($source, '`')) {
                return null;
            }

            try {
                $source = $this->dateFilter->convertDateShortCode($source, $this->apps?->get()->locale);
            } catch (Throwable) {
                return null;
            }
        }

        $rawComments = [];
        if (str_contains($source, '<!--')) {
            $source = preg_replace_callback('/<!--.*?-->/s', static function (array $match) use (&$rawComments): string {
                $rawComments[] = $match[0];

                return "\u{E024}".(\count($rawComments) - 1)."\u{E025}";
            }, $source);
            if (null === $source) {
                return null;
            }
        }

        $rawSpans = [];
        if (str_contains($source, '<span')) {
            $source = preg_replace_callback('/<span\b[^>]*>.*?<\/span>/s', static function (array $match) use (&$rawSpans): string {
                $rawSpans[] = str_replace('&nbsp;', "\u{00A0}", $match[0]);

                return "\u{E007}".(\count($rawSpans) - 1)."\u{E008}";
            }, $source);
            if (null === $source) {
                return null;
            }
        }

        $obfuscatedLinks = [];
        if (str_contains($source, '#[')) {
            $source = preg_replace_callback('/#\[[^][]+\]\([^()\r\n]*\)/', static function (array $match) use (&$obfuscatedLinks): string {
                $obfuscatedLinks[] = $match[0];

                return "\u{E037}".(\count($obfuscatedLinks) - 1)."\u{E038}";
            }, $source);
            if (null === $source) {
                return null;
            }
        }

        $contacts = [];
        if (null !== $this->linkProvider && ! str_contains($source, '`') && ! str_contains($source, '<') && ! str_contains($source, "\u{E000}")) {
            try {
                $emailPattern = '(?<![A-Za-z0-9._+-])(?<email>[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,})(?=$|[ \t\n.,!?)>\/])';
                $phonePattern = '(?<phone>(?:(?:\+|00)33|0)(?:\s|&nbsp;|\xC2\xA0)*[1-9](?:(?:[\s.-]|&nbsp;|\xC2\xA0)*\d{2}){4})(?=$|[ \t\n.,!?);>\/<])';
                $source = preg_replace_callback('/'.$emailPattern.'|'.$phonePattern.'/i', function (array $match) use (&$contacts): string {
                    $contacts[] = '' !== $match['email']
                        ? $this->linkProvider->renderEncodedMail($match['email'])
                        : $this->linkProvider->renderPhoneNumber(trim($match['phone']));

                    return "\u{E000}".(\count($contacts) - 1)."\u{E001}";
                }, $source);
            } catch (Throwable) {
                return null;
            }

            if (null === $source) {
                return null;
            }
        }

        foreach ($obfuscatedLinks as $index => $link) {
            $source = str_replace("\u{E037}".$index."\u{E038}", $link, $source);
        }

        $linkTitles = [];
        $source = preg_replace_callback('/(?<!#)\[([^][\r\n]+)\]\(((?:[^()\s<>]|\\\\[()])+) "([^"\r\n]*)"\)/u', static function (array $match) use (&$linkTitles): string {
            $linkTitles[] = $match[3];

            return '['.$match[1].']('.$match[2].'PWTITLE'.(\count($linkTitles) - 1).'TOKEN)';
        }, $source);
        if (null === $source) {
            return null;
        }

        $source = preg_replace('/(\[[^][\r\n]+\]\([^()\s]+)[ \t]+\)/', '$1)', $source);
        if (null === $source) {
            return null;
        }

        $literalLinks = [];
        $source = preg_replace_callback('/#?\[[^][]+\]\((?!<)([^()\r\n]*\s[^()\r\n]*)\)/', static function (array $match) use (&$literalLinks): string {
            $literalLinks[] = $match[0];

            return "\u{E022}".(\count($literalLinks) - 1)."\u{E023}";
        }, $source);
        if (null === $source) {
            return null;
        }

        $source = preg_replace_callback('/(?<!#)(\[[^][]+\]\()([^()\r\n]+)(\))/u', static function (array $match): string {
            $url = preg_replace_callback('/[^\x21-\x7E]/u', static fn (array $character): string => rawurlencode($character[0]), $match[2]);

            return $match[1].($url ?? $match[2]).$match[3];
        }, $source);
        if (null === $source) {
            return null;
        }

        $links = [];
        if (str_contains($source, '){') || str_contains($source, '#[')) {
            if (str_contains($source, '#[') && null === $this->linkProvider) {
                return null;
            }

            $source = preg_replace_callback('/(#?)(\[[^][]+\]\((<[^>\r\n]+>|(?:[^()\r\n]|\\\\[()])*)\))(?:\{(?:\.([A-Za-z0-9_-]+)|class="([A-Za-z0-9_-]+)"|target="([^"]+)"|rel="([A-Za-z0-9_-]+)")\})?/', static function (array $match) use (&$links): string {
                $attributes = [];
                $dotClass = $match[4] ?? '';
                $namedClass = $match[5] ?? '';
                if ('' !== $dotClass || '' !== $namedClass) {
                    $attributes['class'] = '' !== $dotClass ? $dotClass : $namedClass;
                } elseif ('' !== ($match[6] ?? '')) {
                    $attributes['target'] = $match[6];
                } elseif ('' !== ($match[7] ?? '')) {
                    $attributes['rel'] = $match[7];
                }

                $links[] = ['obfuscated' => '#' === $match[1], 'url' => trim($match[3], '<>'), 'attributes' => $attributes];

                return $match[2];
            }, $source);
            if (null === $source) {
                return null;
            }
        }

        $source = preg_replace('/<(?=\d|\s)/', "\u{E013}", $source);
        if (null === $source) {
            return null;
        }

        $rawTags = [];
        if (str_contains($source, '<')) {
            if (str_contains($source, "\u{E004}") || str_contains($source, "\u{E005}") || 1 === preg_match('/<\/?(?:div|section|figure|article|h[1-6]|ul|li|p|blockquote|table|script|style)\b/i', $source)) {
                return null;
            }

            $source = preg_replace_callback('/<[^>]+>/', static function (array $match) use (&$rawTags): string {
                $rawTags[] = $match[0];

                return "\u{E004}".(\count($rawTags) - 1)."\u{E005}";
            }, $source);
            if (null === $source || str_contains($source, '<')) {
                return null;
            }
        }

        $source = str_replace('&nbsp;', "\u{00A0}", $source);
        if (null === $listTag && str_contains($source, "\n")) {
            $source = preg_replace('/(?m)^([2-9][0-9]*)\. /', '$1'."\u{E026}".' ', $source);
            if (null === $source) {
                return null;
            }
        }

        $source = str_replace(['\\*', '\\[', '\\]', '\\+', '\\-', '\\_', '\\.', '\\>', '\\(', '\\)', '\\`', '_,_', '{', '}'], ["\u{E018}", "\u{E027}", "\u{E028}", "\u{E029}", "\u{E030}", "\u{E009}", "\u{E036}", "\u{E014}", "\u{E015}", "\u{E016}", "\u{E017}", "\u{E032}", "\u{E047}", "\u{E048}"], $source);
        if (str_ends_with($source, '\\')) {
            $source = substr($source, 0, -1)."\u{E046}";
        }

        $source = preg_replace('/(?<=\d)_(?=[A-Za-z0-9]+(?:\s|[,.€]))/', "\u{E009}", $source);
        if (null === $source) {
            return null;
        }

        $source = preg_replace('/(\[_[^][]+_\]\([^()]+\))_(?= )/', '$1'."\u{E009}", $source);
        if (null === $source) {
            return null;
        }

        $validLinks = [];
        if (str_contains($source, '[')) {
            $source = preg_replace_callback('/\[[^][]*\]\((?:<[^>\r\n]+>|(?:[^()\r\n]|\([^()\r\n]*\))*)\)/', static function (array $match) use (&$validLinks): string {
                $validLinks[] = $match[0];

                return "\u{E042}".(\count($validLinks) - 1)."\u{E043}";
            }, $source);
            if (null === $source) {
                return null;
            }

            $source = str_replace(['[', ']'], ["\u{E040}", "\u{E041}"], $source);
        }

        $source = preg_replace('/(?<=[\p{L}\p{N}])_(?=[\p{L}\p{N}])/u', "\u{E009}", $source);
        if (null === $source) {
            return null;
        }

        foreach ($validLinks as $index => $link) {
            $source = str_replace("\u{E042}".$index."\u{E043}", $link, $source);
        }

        $source = preg_replace('/^_([^_\n]+):___/m', "\u{E009}".'$1:'.str_repeat("\u{E009}", 2).'_', $source);
        if (null === $source) {
            return null;
        }

        $source = preg_replace('/(?<!_)_([^_\n]+)__(?=,|\. )/', '_$1_'."\u{E009}", $source);
        if (null === $source) {
            return null;
        }

        $source = preg_replace('/(?<=[\p{L}\p{N}_])_\._/u', "\u{E031}", $source);
        if (null === $source) {
            return null;
        }

        $source = preg_replace('/(?<=\d)\*(?=\/\d)/', "\u{E018}", $source);
        if (null === $source) {
            return null;
        }

        $source = preg_replace('/(?<=\s)\*{3}(?=\s)/', str_repeat("\u{E018}", 3), $source);
        if (null === $source) {
            return null;
        }

        $source = preg_replace('/(?<=\p{L})\*\*(?=[\x27\x{2019}])/u', str_repeat("\u{E018}", 2), $source);
        if (null === $source) {
            return null;
        }

        $source = preg_replace_callback('/\b(?:hôtel|hotel)\s+[1-5]\*{1,4}(?:\s*(?:\/|ou|à|et)\s*[1-5]\*{1,4})?/iu', static fn (array $match): string => str_replace('*', "\u{E018}", $match[0]), $source);
        if (null === $source) {
            return null;
        }

        $source = preg_replace_callback('/\b[1-5]\*(?=\s|[),.;]|$)/m', static function (array $match) use ($source): string {
            $before = substr($source, 0, $match[0][1]);
            $before = preg_replace('/\b[1-5]\*(?=\s|[),.;]|$)/m', '', $before) ?? $before;

            return 0 === substr_count($before, '*') % 2 ? substr($match[0][0], 0, -1)."\u{E018}" : $match[0][0];
        }, $source, flags: \PREG_OFFSET_CAPTURE);
        if (null === $source) {
            return null;
        }

        $source = preg_replace_callback('/(?<=[\p{L}\p{N}])\*{3,4}(?=\s|$)/u', static fn (array $match): string => str_replace('*', "\u{E018}", $match[0]), $source);
        if (null === $source) {
            return null;
        }

        if (1 === preg_match('/^_[^\n]+ _$/D', $source)) {
            $source = str_replace('_', "\u{E020}", $source);
        }

        $source = preg_replace_callback('/\*\*(?! )[^*\n]*\S\*\*(*SKIP)(*F)|\*\*(?! )([^*\n]+) \*\*/', static fn (array $match): string => "\u{E019}".$match[1]." \u{E019}", $source);
        if (null === $source) {
            return null;
        }

        $source = preg_replace_callback('/(?m)^(- )\*\* ([^*\n]+)\*\*/', static fn (array $match): string => $match[1]."\u{E019} ".$match[2]."\u{E019}", $source);
        if (null === $source) {
            return null;
        }

        $source = preg_replace_callback('/\[([A-Z_]+)\](?!\()/', static fn (array $match): string => "\u{E010}".$match[1]."\u{E011}", $source);
        if (null === $source) {
            return null;
        }

        if (! str_contains($source, '[') && str_contains($source, '://') && ! str_contains($source, "\u{E009}")) {
            $source = preg_replace_callback('/https?:\/\/\S+/', static fn (array $match): string => str_replace('_', "\u{E009}", $match[0]), $source);
            if (null === $source) {
                return null;
            }
        }

        $literalTilde = ! $table && str_contains($source, '~');
        if ($literalTilde) {
            if (str_contains($source, "\u{E002}")) {
                return null;
            }

            if (1 === preg_match('/~[^\s~]+~/', $source)) {
                $literalTilde = false;
            } else {
                $source = str_replace('~', "\u{E002}", $source);
            }
        }

        if (null !== $listTag) {
            $pattern = 'ul' === $listTag
                ? '/\A- [^\r\n]+(?:\n- [^\r\n]+)*\n?\z/'
                : '/\A[0-9]{1,9}\. [^\r\n]+(?:\n[0-9]{1,9}\. [^\r\n]+)*\n?\z/';
            if (1 !== preg_match($pattern, $source)) {
                return null;
            }

            foreach (explode("\n", rtrim($source, "\n")) as $item) {
                if ('ol' === $listTag && 1 !== preg_match('/^[0-9]{1,9}\. (.+)$/D', $item, $matches)) {
                    return null;
                }

                $content = 'ul' === $listTag ? substr($item, 2) : $matches[1];
                if (! $this->isCompatibleSingleLine($content, false)) {
                    return null;
                }
            }
        }

        $heading = preg_match('/^(#{1,6})[ \t]+([^\r\n]+)$/D', $source, $matches);
        $content = 1 === $heading ? $matches[2] : $source;
        $multilineParagraph = null === $listTag && ! $table && str_contains($content, "\n");
        if (null === $listTag && ! $table) {
            if ($multilineParagraph) {
                if (1 === preg_match('/\r|\n[ \t]*\n/', $content)) {
                    return null;
                }

                $source = preg_replace('/ {2,}\n/', "\u{E033}\n", $source);
                if (null === $source) {
                    return null;
                }

                $source = str_replace(" \n", "\n", $source);

                foreach (explode("\n", rtrim($source, "\n")) as $line) {
                    if ($line !== rtrim($line) || ! $this->isCompatibleSingleLine($line, false)) {
                        return null;
                    }
                }
            } elseif (! $this->isCompatibleSingleLine($content, 1 === $heading)) {
                return null;
            }
        }

        try {
            $html = $this->markdown->parse($source)->html;
        } catch (Throwable) {
            return null;
        }

        if ($multilineParagraph && (! str_starts_with($html, '<p>') || ! str_ends_with($html, '</p>'))) {
            return null;
        }

        if ($table) {
            if (! str_starts_with($html, '<table><thead><tr>') || ! str_ends_with($html, '</tbody></table>')) {
                return null;
            }

            $html = $this->normalizeTable($html);
            if (null === $html) {
                return null;
            }

            $html = preg_replace_callback('/<td>(.*?)<\/td>/s', static function (array $cell): string {
                $content = preg_replace('/~~([^~<>]+)~~/', '<del>$1</del>', $cell[1]);
                $content = preg_replace('/~([^\s~<>]+)~/', '<del>$1</del>', $content ?? $cell[1]);

                return '<td>'.($content ?? $cell[1]).'</td>';
            }, $html);
            if (null === $html) {
                return null;
            }

            if ($emptyTableHeader) {
                $html = preg_replace('/<thead>.*?<\/thead>/s', '', $html, 1);
                if (null === $html) {
                    return null;
                }
            }
        }

        if (1 === $heading) {
            $html = preg_replace('/^(<h[1-6]) id="[^"]*"/', '$1', $html, 1, $replacements);
            if (null === $html || 1 !== $replacements) {
                return null;
            }
        }

        if (null !== $attribute) {
            $html = preg_replace('/^<(h[1-6]|p)>/', '<$1 '.$attribute.'>', $html, 1, $replacements);
            if (null === $html || 1 !== $replacements) {
                return null;
            }
        }

        if ([] !== $links) {
            $linkIndex = 0;

            try {
                $html = preg_replace_callback('/(<a href="[^"]*"[^>]*>)(.*?)<\/a>/s', function (array $match) use ($links, &$linkIndex): string {
                    $link = $links[$linkIndex++] ?? null;
                    if (null === $link) {
                        return $match[0];
                    }

                    if ($link['obfuscated']) {
                        $rawUrl = str_replace(['\\(', '\\)'], ['(', ')'], $link['url']);
                        $url = preg_replace_callback('/[^\x21-\x7E]/u', static fn (array $character): string => rawurlencode($character[0]), $rawUrl);

                        return $this->linkProvider?->renderLink($match[2], $url ?? $rawUrl, $link['attributes'], true) ?? $match[0];
                    }

                    if ([] === $link['attributes']) {
                        return $match[0];
                    }

                    $name = array_key_first($link['attributes']);
                    $value = $link['attributes'][$name];
                    $openingTag = str_replace('<a ', '<a '.$name.'="'.htmlspecialchars($value, \ENT_QUOTES).'" ', $match[1]);
                    if ('target' === $name && '_blank' === $value) {
                        $openingTag = substr($openingTag, 0, -1).' rel="noopener noreferrer">';
                    }

                    return $openingTag.$match[2].'</a>';
                }, $html);
            } catch (Throwable) {
                return null;
            }

            if (null === $html || $linkIndex !== \count($links)) {
                return null;
            }
        }

        if (null !== $listTag) {
            if (! str_starts_with($html, '<'.$listTag.'><li>') || ! str_ends_with($html, '</li></'.$listTag.'>')) {
                return null;
            }

            if ([] !== $itemClasses) {
                $itemIndex = 0;
                $html = preg_replace_callback('/<li>/', static function () use (&$itemIndex, $itemClasses): string {
                    $class = $itemClasses[$itemIndex++] ?? null;

                    return null === $class ? '<li>' : '<li class="'.$class.'">';
                }, $html);
                if (null === $html || $itemIndex !== \count($itemClasses)) {
                    return null;
                }
            }

            $html = str_replace(
                ['<'.$listTag.'>', '</li></'.$listTag.'>'],
                ['<'.$listTag.">\n", "</li>\n</".$listTag.'>'],
                $html,
            );
            $html = str_replace('</li><li', "</li>\n<li", $html);
            if ('ol' === $listTag && 1 !== $listStart) {
                $html = str_replace('<ol>', '<ol start="'.$listStart.'">', $html);
            }
        }

        if (1 === preg_match('/(?:<strong>|<em>)[ \t]|[ \t]<\/(?:strong|em)>/', $html)) {
            return null;
        }

        // Tempest leaves quotes in text nodes unescaped, unlike CommonMark.
        $parts = preg_split('/(<[^>]+>)/', $html, -1, \PREG_SPLIT_DELIM_CAPTURE);
        if (false === $parts) {
            return null;
        }

        foreach ($parts as $index => $part) {
            if (0 === $index % 2) {
                $escaped = preg_replace('/&(?!#[0-9]+;|#x[0-9A-Fa-f]+;|[A-Za-z][A-Za-z0-9]+;)/', '&amp;', $part);
                if (null === $escaped) {
                    return null;
                }

                $parts[$index] = str_replace(['"', '>'], ['&quot;', '&gt;'], $escaped);
            }
        }

        $html = str_replace(['<s>', '</s>'], ['<del>', '</del>'], implode('', $parts));
        foreach ($contacts as $index => $contact) {
            $html = str_replace("\u{E000}".$index."\u{E001}", $contact, $html);
        }

        if ($literalTilde) {
            $html = str_replace("\u{E002}", '~', $html);
        }

        foreach ($rawTags as $index => $tag) {
            $html = str_replace("\u{E004}".$index."\u{E005}", $tag, $html);
        }

        foreach ($rawSpans as $index => $span) {
            $html = str_replace("\u{E007}".$index."\u{E008}", $span, $html);
        }

        foreach ($rawComments as $index => $comment) {
            $html = str_replace("\u{E024}".$index."\u{E025}", $comment, $html);
        }

        foreach ($literalLinks as $index => $link) {
            $html = str_replace("\u{E022}".$index."\u{E023}", str_replace(['&', '"', '>'], ['&amp;', '&quot;', '&gt;'], $link), $html);
        }

        foreach ($linkTitles as $index => $title) {
            $marker = 'PWTITLE'.$index.'TOKEN';
            if (! str_contains($html, $marker)) {
                return null;
            }

            $html = str_replace($marker, '" title="'.htmlspecialchars($title, \ENT_COMPAT | \ENT_SUBSTITUTE), $html);
        }

        foreach ($inlineImages as $index => $image) {
            if (null === $this->mediaExtension || null === $this->apps) {
                return null;
            }

            $marker = "\u{E034}".$index."\u{E035}";
            if (! str_contains($html, $marker)) {
                return null;
            }

            try {
                $imageHtml = $this->mediaExtension->renderImage($image['src'], htmlspecialchars($image['alt']), link: ! $image['linked'], sizes: $this->apps->get()->bodyImageSizes());
            } catch (Throwable) {
                $imageHtml = BrokenImageComment::for($image['src']);
            }

            $html = str_replace($marker, $imageHtml, $html);
        }

        if (str_contains($html, "\u{E015}") || str_contains($html, "\u{E016}")) {
            $html = preg_replace_callback('/(<a\b[^>]*href=")([^"]+)(")/', static function (array $match): string {
                if (! str_contains($match[2], "\u{E015}") && ! str_contains($match[2], "\u{E016}")) {
                    return $match[0];
                }

                $href = str_replace(["\u{E015}", "\u{E016}"], ['(', ')'], $match[2]);
                $href = preg_replace_callback('/[^\x21-\x7E]/u', static fn (array $character): string => rawurlencode($character[0]), $href) ?? $href;

                return $match[1].$href.$match[3];
            }, $html);
            if (null === $html) {
                return null;
            }
        }

        $html = str_replace("\u{E009}", '_', $html);
        $html = str_replace("\u{E013}", '&lt;', $html);
        $html = str_replace(["\u{E018}", "\u{E019}"], ['*', '**'], $html);
        $html = str_replace(["\u{E027}", "\u{E028}"], ['[', ']'], $html);
        $html = str_replace(["\u{E029}", "\u{E030}"], ['+', '-'], $html);
        $html = str_replace(["\u{E031}", "\u{E032}"], ['_._', '_,_'], $html);
        $html = str_replace("\u{E033}\n", "<br />\n", $html);
        $html = str_replace("\u{E020}", '_', $html);
        $html = str_replace(["\u{E026}", "\u{E036}"], '.', $html);
        $html = str_replace("\u{E014}", '&gt;', $html);
        $html = str_replace(["\u{E015}", "\u{E016}", "\u{E017}"], ['(', ')', '`'], $html);
        $html = str_replace(["\u{E010}", "\u{E011}"], ['[', ']'], $html);
        $html = str_replace(["\u{E040}", "\u{E041}"], ['[', ']'], $html);
        $html = str_replace("\u{E044}", '!', $html);
        $html = str_replace("\u{E046}", '\\', $html);
        $html = str_replace(["\u{E047}", "\u{E048}"], ['{', '}'], $html);
        if ($literalLeadingHash) {
            $html = str_replace("\u{E012}", '#', $html);
        }

        return rtrim($html)."\n";
    }

    private function normalizeTable(string $html): ?string
    {
        if (1 !== preg_match('/<thead><tr>(.*?)<\/tr>/s', $html, $header)) {
            return null;
        }

        $columns = substr_count($header[1], '</th>');

        return preg_replace_callback('/<tr>(.*?)<\/tr>/s', static function (array $row) use ($columns): string {
            preg_match_all('/<(th|td)>(.*?)<\/\1>/s', $row[1], $matches, \PREG_SET_ORDER);
            $normalized = '';
            $tag = null;
            $content = '';
            $span = 1;
            foreach (\array_slice($matches, 0, $columns) as $match) {
                if (\in_array($match[2], ['->', '-&gt;'], true) && null !== $tag) {
                    ++$span;

                    continue;
                }

                if (null !== $tag) {
                    $normalized .= self::renderTableCell($tag, $content, $span);
                }

                $tag = $match[1];
                $content = $match[2];
                $span = 1;
            }

            if (null !== $tag) {
                $normalized .= self::renderTableCell($tag, $content, $span);
            }

            for ($index = \count($matches); $index < $columns; ++$index) {
                $normalized .= '<td></td>';
            }

            return '<tr>'.$normalized.'</tr>';
        }, $html);
    }

    private static function renderTableCell(string $tag, string $content, int $span): string
    {
        $colspan = 1 === $span ? '' : ' colspan="'.$span.'"';

        return '<'.$tag.$colspan.'>'.$content.'</'.$tag.'>';
    }

    /**
     * @param list<array{indent: int, text: string}> $items
     */
    private function renderNestedList(array $items, int &$position, int $indent, bool $loose = false): ?string
    {
        $html = "<ul>\n";
        while (isset($items[$position]) && $items[$position]['indent'] === $indent) {
            $content = $this->render(rtrim($items[$position]['text']));
            if (null === $content) {
                return null;
            }

            if (str_starts_with($content, '<p>') && str_ends_with($content, "</p>\n")) {
                $html .= $loose ? "<li>\n".$content : '<li>'.substr($content, 3, -5);
            } elseif (str_starts_with($content, '<table>') && str_ends_with($content, "</table>\n")) {
                $html .= "<li>\n".$content;
            } else {
                return null;
            }

            ++$position;
            if (isset($items[$position]) && $items[$position]['indent'] > $indent) {
                $nested = $this->renderNestedList($items, $position, $items[$position]['indent']);
                if (null === $nested) {
                    return null;
                }

                $html .= ($loose ? '' : "\n").$nested;
            }

            $html .= "</li>\n";
        }

        return $html."</ul>\n";
    }

    private function renderNotice(string $source): ?string
    {
        $lines = explode("\n", $source);
        if (null === $this->twig || null === $this->apps || 1 !== preg_match('/^> \[!([a-z][a-z0-9_-]*)\] ([^\r\n{}]+?)(?: \{id=([A-Za-z0-9_-]+)\})?$/iD', array_shift($lines), $matches)) {
            return null;
        }

        $body = [];
        foreach ($lines as $line) {
            if ('>' === $line) {
                $body[] = '';
            } elseif (str_starts_with($line, '> ')) {
                $body[] = substr($line, 2);
            } else {
                return null;
            }
        }

        $content = [];
        $blocks = preg_split('/\n[ \t]*\n/', trim(implode("\n", $body), "\n")) ?: [];
        foreach ($blocks as $index => $block) {
            $isList = str_starts_with($block, '- ') || 1 === preg_match('/^[0-9]+\. /', $block);
            $nextIsList = isset($blocks[$index + 1]) && (str_starts_with($blocks[$index + 1], '- ') || 1 === preg_match('/^[0-9]+\. /', $blocks[$index + 1]));

            $html = $this->render($block);
            if (null === $html) {
                return null;
            }

            if ($isList && ($nextIsList || isset($looseList))) {
                if (! str_starts_with($html, "<ul>\n") || ! str_ends_with($html, "</ul>\n")) {
                    return null;
                }

                $items = substr($html, 5, -6);
                $items = preg_replace('/<li([^>]*)>(.*?)<\/li>/s', "<li$1>\n<p>$2</p>\n</li>", $items);
                if (null === $items) {
                    return null;
                }

                $items = preg_replace('/<li class="([^"]+)">\n<p>/', "<li>\n<p class=\"$1\">", $items);
                if (null === $items) {
                    return null;
                }

                $looseList = ($looseList ?? '').$items;
                if (! $nextIsList) {
                    $content[] = "<ul>\n".rtrim($looseList)."\n</ul>";
                    unset($looseList);
                }

                continue;
            }

            $content[] = rtrim($html);
        }

        if ([] === $content) {
            return null;
        }

        try {
            $level = strtolower($matches[1]);
            $site = $this->apps->get();
            $view = $site->getView('/component/notice/'.$level.'.html.twig', '@Pushword');
            if (! $this->twig->getLoader()->exists($view)) {
                $view = $site->getView('/component/notice.html.twig', '@Pushword');
            }

            return $this->twig->render($view, [
                'level' => $level,
                'title' => $matches[2],
                'content' => implode("\n", $content),
                'id' => $matches[3] ?? '',
                'class' => '',
                'params' => [],
            ])."\n";
        } catch (Throwable) {
            return null;
        }
    }

    private function isCompatibleSingleLine(string $source, bool $heading): bool
    {
        if ('' === $source || $source !== ltrim($source) || ! mb_check_encoding($source, 'UTF-8')) {
            return false;
        }

        if (false !== strpbrk($source, "\r\n\t{}<\\") || 1 === preg_match('/[\x00-\x1F\x7F]/', $source)) {
            return false;
        }

        if ((! $heading && 1 === preg_match('/^#{1,6}[ \t]/', $source)) || str_contains($source, '![')) {
            return false;
        }

        if (str_contains($source, '___') || 1 === preg_match('/(^|\s)\*{3}\S/', $source)) {
            return false;
        }

        $withoutLinks = $source;
        if (str_contains($source, '[')) {
            $linkPattern = '/\[[^][]*\]\((<[^>\r\n]+>|(?:[^()\r\n]|\([^()\r\n]*\))*)\)/';
            preg_match_all($linkPattern, $source, $links);
            foreach ($links[1] as $destination) {
                if (1 === preg_match('/[\s\"]/', $destination)) {
                    return false;
                }
            }

            $withoutLinks = preg_replace($linkPattern, '', $source);
            if (null === $withoutLinks || false !== strpbrk($withoutLinks, '[]')) {
                return false;
            }
        }

        if (1 === preg_match('/[\p{L}\p{N}]_[\p{L}\p{N}]+_[\p{L}\p{N}]/u', $withoutLinks)) {
            return false;
        }

        if (1 === preg_match('/(?:date\(|^[-=]{3,}\s*$)/i', $source)) {
            return false;
        }

        if (null === $this->linkProvider && 1 === preg_match('/(?:\+33[ .-]?[1-9](?:[ .-]?\d{2}){4}|(?<!\d)0[1-9](?:[ .-]?\d{2}){4}(?!\d))/', $source)) {
            return false;
        }

        return $heading || 0 === preg_match('/^(?:\d+[.)]|[-+*])\s/', $source);
    }
}
