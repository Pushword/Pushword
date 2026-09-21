<?php

declare(strict_types=1);

namespace Pushword\Core\Service\Markdown;

use Closure;
use Pushword\Core\Service\LinkProvider;
use Pushword\Core\Site\SiteConfig;
use Pushword\Core\Site\SiteRegistry;

/** Handles complete Pushword syntax forms before generic block parsing. */
final readonly class TempestStandaloneRenderer
{
    /**
     * @param Closure(string): ?string $renderMarkdown
     * @param Closure(string): ?string $renderInline
     */
    public function __construct(private ?LinkProvider $linkProvider, private ?SiteRegistry $apps, private TempestNoticeRenderer $notices, private Closure $renderMarkdown, private Closure $renderInline)
    {
    }

    /** False means no rule matched; null means a matched rule cannot be rendered. */
    public function tryRender(string $source): string|false|null
    {
        if (1 === preg_match('/\A\{([^{}\n]+)\}\n(> \[![\s\S]+)\z/D', $source, $attributedNotice)) {
            $attributes = $this->notices->parseAttributes($attributedNotice[1]);

            return null === $attributes ? null : $this->notices->render($attributedNotice[2], $attributes);
        }

        if (1 === preg_match('/\A(#{1,6} [^\n]+) \{\.([A-Za-z0-9_-]+) #([A-Za-z0-9_-]+)\}\z/D', $source, $headingAttributes)) {
            $heading = ($this->renderMarkdown)($headingAttributes[1]);
            if (null === $heading) {
                return null;
            }

            $tag = 'h'.strspn($headingAttributes[1], '#');

            return str_replace('<'.$tag.'>', '<'.$tag.' class="'.$headingAttributes[2].'" id="'.$headingAttributes[3].'">', $heading);
        }

        if (1 === preg_match('/\A\{#([A-Za-z0-9_-]+)\}\n(#{1,6} [^\n]+)\z/D', $source, $leadingHeadingId)) {
            $heading = ($this->renderMarkdown)($leadingHeadingId[2]);
            $tag = 'h'.strspn($leadingHeadingId[2], '#');

            return null === $heading ? null : str_replace('<'.$tag.'>', '<'.$tag.' id="'.$leadingHeadingId[1].'">', $heading);
        }

        if (1 === preg_match('/\A(#{1,6} [^\n]+)\n\{\.([A-Za-z0-9_-]+)\}\z/D', $source, $trailingHeadingClass)) {
            return ($this->renderMarkdown)($trailingHeadingClass[1].' {.'.$trailingHeadingClass[2].'}');
        }

        if (1 === preg_match('/\A([^\n]+) \{\.([A-Za-z0-9_-]+) #([A-Za-z0-9_-]+)\}\n-{3,}\z/D', $source, $setextAttributes)) {
            return ($this->renderMarkdown)('## '.$setextAttributes[1].' {.'.$setextAttributes[2].' #'.$setextAttributes[3].'}');
        }

        if (1 === preg_match('/\A\{id=[A-Za-z0-9_-]+\}\n\n([\s\S]+)\z/D', $source, $separatedAttribute)) {
            return ($this->renderMarkdown)($separatedAttribute[1]);
        }

        if (1 === preg_match('/\A\{[.#][^{}\n]+\}\n\n([\s\S]+)\z/D', $source, $separatedAttribute)) {
            return ($this->renderMarkdown)($separatedAttribute[1]);
        }

        if (1 === preg_match('/\A<([^<>\s]+@[^<>\s]+|tel:[^<>\s]+)>\z/D', $source, $autoLink)) {
            $url = str_starts_with($autoLink[1], 'tel:') ? $autoLink[1] : 'mailto:'.$autoLink[1];

            return '<p><a href="'.htmlspecialchars($url, \ENT_QUOTES | \ENT_SUBSTITUTE).'">'.htmlspecialchars($autoLink[1], \ENT_QUOTES | \ENT_SUBSTITUTE)."</a></p>\n";
        }

        if (1 === preg_match('/\A`([^`\n]+)`(?:\{([^{}\n]+)\})?\z/D', $source, $attributedCode)) {
            $attributes = [];
            if (isset($attributedCode[2])) {
                preg_match_all('/\.[A-Za-z0-9_-]+|#[A-Za-z0-9_-]+|class="[^"]*"/', $attributedCode[2], $tokens);
                if (implode(' ', $tokens[0]) !== $attributedCode[2]) {
                    return null;
                }

                foreach ($tokens[0] as $token) {
                    if ('.' === $token[0]) {
                        $attributes['class'] = substr($token, 1);
                    } elseif ('#' === $token[0]) {
                        $attributes['id'] = substr($token, 1);
                    } elseif ('class=""' === $token) {
                        unset($attributes['class']);
                    }
                }
            }

            $opening = '<code';
            foreach ($attributes as $name => $value) {
                $opening .= ' '.$name.'="'.$value.'"';
            }

            return '<p>'.$opening.'>'.htmlspecialchars($attributedCode[1], \ENT_NOQUOTES | \ENT_SUBSTITUTE)."</code></p>\n";
        }

        if (1 === preg_match('/\A(#?)\[([^\]\n]+)\]\(([^()\s\n]+)\)\{([^{}\n]+)\}\z/D', $source, $attributedLink)
            && (str_contains($attributedLink[4], ' ') || str_starts_with($attributedLink[4], '#'))
            && ! str_contains($attributedLink[4], "'")
            && 1 !== preg_match('/[^\x00-\x7F]/', $attributedLink[4])) {
            preg_match_all('/\.[A-Za-z0-9_:-]+|#[A-Za-z0-9_-]+|[a-z][a-z0-9_-]*="[^"]*"/i', $attributedLink[4], $tokens);
            if (implode(' ', $tokens[0]) !== $attributedLink[4]) {
                return null;
            }

            $attributes = [];
            foreach ($tokens[0] as $token) {
                if ('.' === $token[0]) {
                    $attributes['class'] = trim(($attributes['class'] ?? '').' '.substr($token, 1));
                } elseif ('#' === $token[0]) {
                    $attributes['id'] = substr($token, 1);
                } else {
                    [$name, $value] = explode('=', $token, 2);
                    if ('class' === $name) {
                        $attributes['class'] = trim(($attributes['class'] ?? '').' '.trim($value, '"'));
                    } elseif ('href' !== $name && ! str_starts_with(strtolower($name), 'on')) {
                        $attributes[$name] = trim($value, '"');
                    }
                }
            }

            $link = ($this->renderMarkdown)('['.$attributedLink[2].']('.$attributedLink[3].')');
            if (null === $link || 1 !== preg_match('/\A<p><a href="([^"]*)">(.*)<\/a><\/p>\n\z/sD', $link, $parsed)) {
                return null;
            }

            if ('#' === $attributedLink[1]) {
                return null === $this->linkProvider ? null : '<p>'.$this->linkProvider->renderLink($parsed[2], $parsed[1], $attributes, true)."</p>\n";
            }

            if ('_blank' === ($attributes['target'] ?? null) && ! isset($attributes['rel'])) {
                $attributes['rel'] = 'noopener noreferrer';
            }

            $opening = '<a';
            foreach ($attributes as $name => $value) {
                $opening .= ' '.$name.'="'.htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE).'"';
            }

            return '<p>'.$opening.' href="'.$parsed[1].'">'.$parsed[2]."</a></p>\n";
        }

        if (1 === preg_match('/\A(\[[^\]\n]+\]\([^()\n]+\))\{(?:title=\'([^\'\n]*)\'|#([\p{L}\p{N}_-]+))\}\z/uD', $source, $singleAttribute)) {
            $html = ($this->renderMarkdown)($singleAttribute[1]);
            if (null === $html || ! str_starts_with($html, '<p><a href=')) {
                return null;
            }

            $name = isset($singleAttribute[3]) ? 'id' : 'title';
            $value = $singleAttribute[3] ?? $singleAttribute[2] ?? '';

            return str_replace('<a href=', '<a '.$name.'="'.htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE).'" href=', $html);
        }

        if (1 === preg_match('/\A(\[[^\]\n]+\]\([^()\n]+ "[^"\n]+"\))\{(data-[a-z-]+="[^"]+"(?: [.#][A-Za-z0-9_-]+)+)\}\z/D', $source, $titledAttributedLink)) {
            $html = ($this->renderMarkdown)($titledAttributedLink[1]);
            if (null === $html || ! str_starts_with($html, '<p><a ')) {
                return null;
            }

            preg_match_all('/data-[a-z-]+="[^"]+"|[.#][A-Za-z0-9_-]+/', $titledAttributedLink[2], $tokens);
            $attributes = [];
            foreach ($tokens[0] as $token) {
                if ('.' === $token[0]) {
                    $attributes['class'] = substr($token, 1);
                } elseif ('#' === $token[0]) {
                    $attributes['id'] = substr($token, 1);
                } else {
                    [$name, $value] = explode('=', $token, 2);
                    $attributes[$name] = trim($value, '"');
                }
            }

            $opening = '<a';
            foreach ($attributes as $name => $value) {
                $opening .= ' '.$name.'="'.htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE).'"';
            }

            return str_replace('<a ', $opening.' ', $html);
        }

        if (1 === preg_match('/\A\[([^\]\n]+)\]\(([^()\s\n]*"[^()\s\n]*)\)\z/D', $source, $quotedDestination)) {
            return ($this->renderMarkdown)('['.$quotedDestination[1].']('.str_replace('"', '%22', $quotedDestination[2]).')');
        }

        if (1 === preg_match('/\A\[([^\]\n]+)\]\(<([^<>\n]+)>(?: "([^"\n]*)")?\)\z/D', $source, $angleDestination)) {
            $url = preg_replace_callback('/%(?![0-9A-Fa-f]{2})|[ \[\]]/', static fn (array $match): string => rawurlencode($match[0]), $angleDestination[2]);
            if (null === $url) {
                return null;
            }

            return ($this->renderMarkdown)('['.$angleDestination[1].']('.$url.(isset($angleDestination[3]) ? ' "'.$angleDestination[3].'"' : '').')');
        }

        if (1 === preg_match('/\A<(?:input|hr|br|img)\b[^>]*>\z/iD', $source)) {
            return $source."\n";
        }

        if (1 === preg_match('/\A<(section|script|style)\b[^>]*>[\s\S]*<\/\1>\z/iD', $source)) {
            return $source."\n";
        }

        if (1 === preg_match('/\A(`{3,}|~{3,})([^\r\n]*)\r?\n([\s\S]*?)\r?\n {0,3}(`{3,}|~{3,})[ \t]*(?:\r?\n(?:\r?\n)?([\s\S]*))?\z/D', $source, $fence)) {
            if ($fence[1][0] !== $fence[4][0] || \strlen($fence[4]) < \strlen($fence[1])) {
                return null;
            }

            $language = strtok(trim($fence[2]), " \t");
            $codeAttribute = false === $language ? '' : ' class="'.htmlspecialchars(str_starts_with($language, 'language-') ? $language : 'language-'.$language, \ENT_QUOTES | \ENT_SUBSTITUTE).'"';
            $preClass = $this->apps?->get()->getStr(SiteConfig::FENCED_CODE_PRE_CLASS) ?? '';
            $preAttribute = '' === $preClass ? '' : ' class="'.htmlspecialchars($preClass, \ENT_QUOTES | \ENT_SUBSTITUTE).'"';
            $html = '<pre'.$preAttribute.'><code'.$codeAttribute.'>'.htmlspecialchars($fence[3]."\n", \ENT_NOQUOTES | \ENT_SUBSTITUTE)."</code></pre>\n";
            $following = isset($fence[5]) && '' !== $fence[5] ? ($this->renderMarkdown)($fence[5]) : '';

            return null === $following ? null : $html.$following;
        }

        if (1 === preg_match('/\A(?: {4}[^\n]*(?:\n|\z))+\z/D', $source)) {
            $code = preg_replace('/(?m)^ {4}/', '', rtrim($source, "\n"));

            return null === $code ? null : '<pre><code>'.htmlspecialchars($code, \ENT_NOQUOTES | \ENT_SUBSTITUTE)."\n</code></pre>\n";
        }

        if (1 === preg_match('/\A[-*+] \[([xX ])\] ([^\n]+)\n((?:  [-*+] [^\n]+(?:\n|\z))+)\z/D', $source, $nestedTasks)) {
            $parent = ($this->renderInline)($nestedTasks[2]);
            if (null === $parent) {
                return null;
            }

            $html = "<ul>\n<li>".$this->taskCheckbox($nestedTasks[1]).' '.$parent."\n<ul>\n";
            foreach (explode("\n", rtrim($nestedTasks[3], "\n")) as $line) {
                if (1 !== preg_match('/^  [-*+] (?:\[([xX ])\] )?(.*)$/D', $line, $child)) {
                    return null;
                }

                $text = ($this->renderInline)($child[2]);
                if (null === $text) {
                    return null;
                }

                $html .= '<li>'.('' !== $child[1] ? $this->taskCheckbox($child[1]).' ' : '').$text."</li>\n";
            }

            return $html."</ul>\n</li>\n</ul>\n";
        }

        if (1 === preg_match('/\A[-*+] \[([xX ])\] ([^\n]+)\n\n  ([^\n]+)\n\n[-*+] \[([xX ])\] ([^\n]+)\z/D', $source, $continuedTasks)) {
            $first = ($this->renderInline)($continuedTasks[2]);
            $continuation = ($this->renderInline)($continuedTasks[3]);
            $last = ($this->renderInline)($continuedTasks[5]);
            if (in_array(null, [$first, $continuation, $last], true)) {
                return null;
            }

            return "<ul>\n<li>\n<p>".$this->taskCheckbox($continuedTasks[1]).' '.$first."</p>\n<p>".$continuation."</p>\n</li>\n<li>\n<p>".$this->taskCheckbox($continuedTasks[4]).' '.$last."</p>\n</li>\n</ul>\n";
        }

        if (1 === preg_match('/^[-*+] \[[xX ]\] /', $source) && 1 === preg_match('/\n {6,}\S/', $source)) {
            $items = [];
            foreach (explode("\n", rtrim($source, "\n")) as $line) {
                if (1 === preg_match('/^[-*+] \[([xX ])\] (.+)$/D', $line, $item)) {
                    $items[] = ['checked' => $item[1], 'text' => $item[2]];
                } elseif ([] !== $items && 1 === preg_match('/^ {6,}(\S.*)$/D', $line, $continuation)) {
                    $items[array_key_last($items)]['text'] .= "\n".$continuation[1];
                } else {
                    return null;
                }
            }

            $html = "<ul>\n";
            foreach ($items as $item) {
                $text = ($this->renderMarkdown)($item['text']);
                if (null === $text || ! str_starts_with($text, '<p>') || ! str_ends_with($text, "</p>\n")) {
                    return null;
                }

                $html .= '<li>'.$this->taskCheckbox($item['checked']).' '.substr($text, 3, -5)."</li>\n";
            }

            return $html."</ul>\n";
        }

        if (1 === preg_match('/\A(?:[-*+] \[[xX ]\] [^\n]+)(?:\n(?:\n)?[-*+] \[[xX ]\] [^\n]+)*\z/D', $source)) {
            $loose = str_contains($source, "\n\n");
            $html = "<ul>\n";
            foreach (preg_split('/\n\n?/', $source) ?: [] as $line) {
                $text = ($this->renderMarkdown)(substr($line, 6));
                if (null === $text || ! str_starts_with($text, '<p>') || ! str_ends_with($text, "</p>\n")) {
                    return null;
                }

                $input = $this->taskCheckbox($line[3]);
                $item = $input.' '.substr($text, 3, -5);
                $html .= $loose ? "<li>\n<p>".$item."</p>\n</li>\n" : '<li>'.$item."</li>\n";
            }

            return $html."</ul>\n";
        }

        if (1 === preg_match('/\A(\|[^\n]+\|)\n(\|[ :|\-]+\|)\n((?:\|[^\n]+\|(?:\n|\z))+)/D', $source, $alignedTable)
            && str_contains($alignedTable[2], ':')) {
            $separators = explode('|', trim($alignedTable[2], '|'));
            $alignments = [];
            foreach ($separators as $separator) {
                $separator = trim($separator);
                $alignments[] = match (true) {
                    str_starts_with($separator, ':') && str_ends_with($separator, ':') => 'center',
                    str_starts_with($separator, ':') => 'left',
                    str_ends_with($separator, ':') => 'right',
                    default => null,
                };
            }

            $plain = $alignedTable[1]."\n|".implode('|', array_fill(0, \count($alignments), '---'))."|\n".$alignedTable[3];
            $html = ($this->renderMarkdown)($plain);
            if (null === $html || ! str_starts_with($html, '<table>')) {
                return null;
            }

            return preg_replace_callback('/<tr>(.*?)<\/tr>/s', static function (array $row) use ($alignments): string {
                $column = 0;
                $cells = preg_replace_callback('/<(th|td)([^>]*)>/', static function (array $cell) use ($alignments, &$column): string {
                    $align = $alignments[$column++] ?? null;

                    return '<'.$cell[1].$cell[2].(null === $align ? '' : ' align="'.$align.'"').'>';
                }, $row[1]);

                return '<tr>'.$cells.'</tr>';
            }, $html);
        }

        if (1 === preg_match('/^> \[![a-z][a-z0-9_-]*\](?: |\r?\n|$)/i', $source)) {
            return $this->notices->render($source);
        }

        return false;
    }

    private function taskCheckbox(string $marker): string
    {
        return ' ' === $marker ? '<input disabled="" type="checkbox">' : '<input checked="" disabled="" type="checkbox">';
    }
}
