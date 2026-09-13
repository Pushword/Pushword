<?php

declare(strict_types=1);

namespace Pushword\Core\Service\Markdown;

use Closure;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Twig\MediaExtension;
use Tempest\Markdown\Markdown;
use Throwable;

/** Composes Markdown blocks, lists and media before the Tempest parser runs. */
final readonly class TempestBlockRenderer
{
    /** @param Closure(string): ?string $renderMarkdown */
    public function __construct(private Markdown $markdown, private ?SiteRegistry $apps, private ?MediaExtension $mediaExtension, private TempestImageRestorer $images, private TempestParsedMarkdownRenderer $parsed, private Closure $renderMarkdown)
    {
    }

    public function render(string $source): ?string
    {
        if (1 === preg_match('/\n[ \t]*\n/', $source) && 1 !== preg_match('/^(?:[-*+] |[0-9]+[.)] |>|`{3}|~{3}|\{id=)/', $source)) {
            $blocks = preg_split('/\n[ \t]*\n+/', trim($source, "\n"));
            if (false === $blocks) {
                return null;
            }

            $html = '';
            foreach ($blocks as $block) {
                $part = ($this->renderMarkdown)(rtrim($block));
                if (null === $part) {
                    return null;
                }

                $html .= $part;
            }

            return $html;
        }

        if (1 === preg_match('/\A((?:[-*+] [^\n]+\n)*[-*+] [^\n]+)\n\n(> [\s\S]+)\z/D', $source, $listAndQuote)) {
            $list = ($this->renderMarkdown)($listAndQuote[1]);
            $quote = ($this->renderMarkdown)($listAndQuote[2]);

            return null === $list || null === $quote ? null : $list.$quote;
        }

        if (1 !== preg_match('/^(?:[-*+] |[0-9]+[.)] |>|`{3}|~{3})/', $source) && 1 === preg_match('/\n#{1,6} /', $source)) {
            $blocks = preg_split('/\n(?=#{1,6} )/', $source);
            if (false !== $blocks && \count($blocks) >= 3) {
                $html = '';
                foreach ($blocks as $block) {
                    $part = ($this->renderMarkdown)($block);
                    if (null === $part) {
                        return null;
                    }

                    $html .= $part;
                }

                return $html;
            }
        }

        $firstLine = strstr($source, "\n", true);
        if (false !== $firstLine && str_contains($source, "\n    ") && 1 !== preg_match('/^(?:[-*+] |[0-9]+[.)] |>)/', $source) && (str_ends_with($firstLine, '  ') || str_starts_with($firstLine, '|'))) {
            $source = preg_replace('/(?m)^ {4}(?=\S)/', '', $source) ?? $source;
        }

        if (1 !== preg_match('/^(?:[-*+] |[0-9]+[.)] |>|\{id=)/', $source) && 1 === preg_match('/\A(.+?)\n((?:- |1[.)] )[^\n]+[\s\S]*)\z/sD', $source, $mixedBlocks)) {
            $before = ($this->renderMarkdown)(rtrim($mixedBlocks[1]));
            $after = ($this->renderMarkdown)($mixedBlocks[2]);

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
            $following = isset($block[2]) ? ($this->renderMarkdown)($block[2]) : '';

            return null === $following ? null : $literal.$following;
        }

        if (1 === preg_match('/\A\{\.([A-Za-z0-9_-]+)\}\n(\|[^\n]+\|\n\|[\s|:-]+\|\n[\s\S]+)\z/D', $source, $block)) {
            $table = ($this->renderMarkdown)($block[2]);

            return null !== $table && str_starts_with($table, '<table>')
                ? str_replace('<table>', '<table class="'.$block[1].'">', $table)
                : null;
        }

        if (1 === preg_match('/\A\{id=([A-Za-z0-9_-]+)\}\n(.+)\z/sD', $source, $block)) {
            if (1 === preg_match('/^<!--[\s\S]*-->$/D', $block[2])) {
                return rtrim($block[2])."\n";
            }

            $html = ($this->renderMarkdown)($block[2]);
            if (null === $html) {
                return null;
            }

            $html = preg_replace('/^<(h[1-6]|p|ul|ol|blockquote)>/', '<$1 id="'.$block[1].'">', $html, 1, $count);

            return 1 === $count ? $html : null;
        }

        if (1 === preg_match('/^(\[![A-Za-z0-9_-]+\] [^\n{}]+) \{id=([A-Za-z0-9_-]+)\}$/D', $source, $label)) {
            $html = ($this->renderMarkdown)($label[1]);

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
            $first = ($this->renderMarkdown)($imageList[1]);
            $second = ($this->renderMarkdown)($imageList[2]);
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

        if (1 === preg_match('/^<!--[\s\S]*-->$/D', $source)) {
            return rtrim($source)."\n";
        }

        if (1 === preg_match('/\A<h([1-6])\b[^>]*>[\s\S]*<\/h\1>(?:\n<div\b[^\n]*>)?\z/D', $source)) {
            return rtrim($source)."\n";
        }

        if (1 === preg_match('/\A(.+)\n((?:<h[1-6]\b|<!--)[\s\S]+)\z/sD', $source, $blocks)) {
            $before = ($this->renderMarkdown)($blocks[1]);
            $after = ($this->renderMarkdown)($blocks[2]);

            if (null !== $before && null !== $after) {
                return $before.$after;
            }
        }

        if (1 === preg_match('/\A(.+)\n(<\/?div\b[^\n]*>)\z/sD', $source, $blocks)) {
            $content = ($this->renderMarkdown)($blocks[1]);

            return null === $content ? null : $content.$blocks[2]."\n";
        }

        if (1 === preg_match('/\A([^\n|]+)\n(\|[^\n]+\|\n\|[\s|:-]+\|\n[\s\S]+)\z/D', $source, $blocks)) {
            $before = ($this->renderMarkdown)($blocks[1]);
            $table = ($this->renderMarkdown)($blocks[2]);

            return null === $before || null === $table ? null : $before.$table;
        }

        if (1 === preg_match('/\A(<\/?div\b[^\n]*>)\n(.+)\z/sD', $source, $blocks)) {
            $content = ($this->renderMarkdown)($blocks[2]);

            return null === $content ? null : $blocks[1]."\n".$content;
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

            $content = ($this->renderMarkdown)(implode("\n", $lines));

            return null === $content ? null : "<blockquote>\n".$content."</blockquote>\n";
        }

        if (! str_starts_with($source, '- ') && 1 === preg_match('/\A(.+?)\n((?:- [^\n]+(?:\n|$))+?)\z/sD', $source, $blocks)) {
            $intro = ($this->renderMarkdown)(rtrim($blocks[1]));
            $list = ($this->renderMarkdown)($blocks[2]);

            return null === $intro || null === $list ? null : $intro.$list;
        }

        if (1 === preg_match('/\A((?:- [^\n]+\n)+)(#{1,6} [^\n]+)\z/D', $source, $blocks)) {
            $list = ($this->renderMarkdown)($blocks[1]);
            $heading = ($this->renderMarkdown)($blocks[2]);

            return null === $list || null === $heading ? null : $list.$heading;
        }

        if (1 === preg_match('/\A(#{1,6} [^\n]+)\n(.+)\z/sD', $source, $blocks)) {
            $heading = ($this->renderMarkdown)($blocks[1]);
            $content = ($this->renderMarkdown)($blocks[2]);

            return null === $heading || null === $content ? null : $heading.$content;
        }

        if (! str_starts_with($source, '{') && 1 === preg_match('/\A([^\n]+)\n(?:[ \t]*\n)?(#{1,6} [^\n]+)\z/D', $source, $blocks)) {
            $before = ($this->renderMarkdown)($blocks[1]);
            $after = ($this->renderMarkdown)($blocks[2]);

            return null === $before || null === $after ? null : $before.$after;
        }

        if (1 === preg_match('/(?m)^([0-9]{1,9})\) /', $source, $firstItem, \PREG_OFFSET_CAPTURE)) {
            if (1 === preg_match('/(?m)^[0-9]{1,9}\)\S/', $source, $malformed, \PREG_OFFSET_CAPTURE)
                && $malformed[0][1] < $firstItem[0][1]) {
                $literal = preg_replace('/(?m)^([0-9]{1,9})\) /', '$1'."\u{E045}".' ', $source);
                $html = null === $literal ? null : ($this->renderMarkdown)($literal);

                return null === $html ? null : str_replace("\u{E045}", ')', $html);
            }

            if (0 !== $firstItem[0][1] && '1' !== $firstItem[1][0]) {
                $literal = preg_replace('/(?m)^([2-9][0-9]*)\) /', '$1'."\u{E045}".' ', $source);
                $html = null === $literal ? null : ($this->renderMarkdown)($literal);

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
                $html = ($this->renderMarkdown)(rtrim(implode("\n", $intro)));
                if (null === $html) {
                    return null;
                }
            }

            $html .= 1 === $start ? "<ol>\n" : '<ol start="'.$start.'">'."\n";
            foreach ($items as $item) {
                $content = ($this->renderMarkdown)(rtrim($item));
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
                $content = ($this->renderMarkdown)($item);
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
                $content = ($this->renderMarkdown)($item);
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
            $html = null === $table ? null : ($this->renderMarkdown)($table);

            return null !== $html && str_starts_with($html, '<table>') ? "<ul>\n<li>\n".$html."</li>\n</ul>\n" : null;
        }

        if (str_starts_with($source, '* |') && str_contains($source, "\n|")) {
            $content = ($this->renderMarkdown)(str_replace('|', "\u{E049}", substr($source, 2)));
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

                $content = ($this->renderMarkdown)(substr($line, 2));
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
                $hadBlank = $blank;
                if ($blank) {
                    if (! $itemStart && ([] === $items || 1 !== preg_match('/^ {4,}\S/', $line))) {
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
                    $items[array_key_last($items)] .= ($hadBlank ? "\n\n" : "\n").$continuation[1];
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

                $content = ($this->renderMarkdown)(rtrim($item));
                if (null === $content || ! str_starts_with($content, '<p>') || ! str_ends_with($content, "</p>\n")) {
                    return null;
                }

                $code = isset($codeBlocks[$index]) ? '<pre><code>'.htmlspecialchars($codeBlocks[$index], \ENT_NOQUOTES | \ENT_SUBSTITUTE)."</code></pre>\n" : '';
                $html .= $loose ? "<li>\n".$content.$code."</li>\n" : '<li>'.substr($content, 3, -5)."</li>\n";
            }

            return $this->images->restore($html."</ul>\n", $inlineImages);
        }

        return $this->parsed->render($source, $inlineImages);
    }

    /** @param list<array{indent: int, text: string}> $items */
    private function renderNestedList(array $items, int &$position, int $indent, bool $loose = false): ?string
    {
        $html = "<ul>\n";
        while (isset($items[$position]) && $items[$position]['indent'] === $indent) {
            $content = ($this->renderMarkdown)(rtrim($items[$position]['text']));
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
}
