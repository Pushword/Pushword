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

/** Uses Tempest for the subset whose serialization matches Pushword's HTML. */
final readonly class TempestMarkdownRenderer
{
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

        if (1 === preg_match('/^(#{1,6}) ([\p{L}\p{N} -]+)$/Du', $source, $plainHeading)) {
            $level = \strlen($plainHeading[1]);

            return '<h'.$level.'>'.htmlspecialchars(trim($plainHeading[2]), \ENT_QUOTES | \ENT_SUBSTITUTE).'</h'.$level.">\n";
        }

        if (1 === preg_match('/^(?:\*[ \t]*){3,}$/D', $source)) {
            return "<hr />\n";
        }

        // Ambiguous delimiter runs do not have the same binding in Tempest and CommonMark.
        if (str_contains($source, '__')
            || str_contains($source, '\\*\\*')
            || str_contains($source, '\\"')
            || 1 === preg_match('/\d_[^_\n]+_/', $source)
            || 1 === preg_match('/[\p{L}\p{N}]_[.!?] {2,}\n[^\n]*_/u', $source)
            || 1 === preg_match('/[\p{L}\p{N}]_\x27[^\s_]+_|_[\p{L}]_[\p{L}]|\b[A-Z]_[a-z]|_\x{200B}_|\b\p{L}\*\*[\x27\x{2019}]|\*\*\(|_[^_\n]+\(_|\*\*,[^*\n]{0,40}\*\*/u', $source)
            || 1 === preg_match('/^_[^\n]*\*\*|#\[[^]]+@|`<[^`]+>`|^\* .*_[0-9]/m', $source)
        ) {
            return null;
        }

        if (1 === preg_match('/\A\{[^\n]+\}\n(<!--[\s\S]*-->)\z/D', $source, $block)) {
            return $this->render($block[1]);
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
            if (str_contains($image[2], ' ') || str_contains($image[1], '_')) {
                return null;
            }

            if (null === $this->mediaExtension || null === $this->apps || ! str_contains($this->markdown->parse($source)->html, '<img ')) {
                return null;
            }

            try {
                $html = $this->mediaExtension->renderImage($image[2], htmlspecialchars($image[1]), link: true, sizes: $this->apps->get()->bodyImageSizes());
            } catch (Throwable) {
                $html = BrokenImageComment::for($image[2]);
            }

            return '<p>'.$html."</p>\n";
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

        if (1 === preg_match('/(?m)^ {2,}[-*] +/', $source)) {
            $items = [];
            foreach (explode("\n", rtrim($source, "\n")) as $line) {
                if (1 !== preg_match('/^( *)(?:[-*]) +(.+)$/D', $line, $match)) {
                    return null;
                }

                $items[] = ['indent' => \strlen($match[1]), 'text' => rtrim($match[2])];
            }

            if (0 !== $items[0]['indent']) {
                return null;
            }

            $position = 0;
            $html = $this->renderNestedList($items, $position, 0);

            return \count($items) === $position ? $html : null;
        }

        if (1 === preg_match('/^[0-9]+\. /', $source) && str_contains($source, "\n   ")) {
            $items = [];
            foreach (explode("\n", rtrim($source, "\n")) as $line) {
                if (1 === preg_match('/^[0-9]+\. (.+)$/D', $line, $match)) {
                    $items[] = $match[1];
                } elseif ([] !== $items && str_starts_with($line, '   ')) {
                    $items[array_key_last($items)] .= "\n".substr($line, 3);
                } else {
                    return null;
                }
            }

            $html = "<ol>\n";
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

        if ((str_starts_with($source, '* ') || str_starts_with($source, '- ')) && (str_starts_with($source, '* ') || str_contains($source, "\n  "))) {
            $items = [];
            foreach (explode("\n", rtrim($source, "\n")) as $line) {
                if (str_starts_with($line, '* ') || str_starts_with($line, '- ')) {
                    $items[] = substr($line, 2);
                } elseif ([] !== $items && str_starts_with($line, '  ')) {
                    $items[array_key_last($items)] .= "\n".substr($line, 2);
                } else {
                    return null;
                }
            }

            $html = "<ul>\n";
            foreach ($items as $item) {
                $content = $this->render($item);
                if (null === $content || ! str_starts_with($content, '<p>') || ! str_ends_with($content, "</p>\n")) {
                    return null;
                }

                $html .= '<li>'.substr($content, 3, -5)."</li>\n";
            }

            return $html."</ul>\n";
        }

        if (1 === preg_match('/^-{3,}\n?$/D', $source)) {
            return str_replace('<hr/>', '<hr />', rtrim($this->markdownWithoutFrontMatter->parse($source)->html))."\n";
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

        $table = 1 === preg_match('/^\|[^\n]+\|\n\|([\s|:-]+)\|\n(?:\|[^\n]+\|[ \t]*\n?)+$/D', $source, $tableMatches)
            && ! str_contains($tableMatches[1], ':')
            && ! str_contains($source, '{');
        if ($table && 1 === preg_match('/\d_\d/', $source)) {
            return null;
        }

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

        $contacts = [];
        if (null !== $this->linkProvider && ! str_contains($source, '`') && ! str_contains($source, '<') && ! str_contains($source, "\u{E000}")) {
            try {
                $source = preg_replace_callback('/(?<![A-Za-z0-9._+-])(?<email>[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,})(?=$|[ \t\n.,!?)>\/])|(?<phone>(?:(?:\+|00)33|0)(?:\s|&nbsp;|\xC2\xA0)*[1-9](?:(?:[\s.-]|&nbsp;|\xC2\xA0)*\d{2}){4})(?=$|[ \t\n.,!?);>\/<])/i', function (array $match) use (&$contacts): string {
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

        $linkTitles = [];
        $source = preg_replace_callback('/(?<!#)\[([^][\r\n]+)\]\(([^()\s<>]+) "([^"\r\n]*)"\)/u', static function (array $match) use (&$linkTitles): string {
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

            $source = preg_replace_callback('/(#?)(\[[^][]+\]\((<[^>\r\n]+>|[^()\r\n]*)\))(?:\{(?:\.([A-Za-z0-9_-]+)|class="([A-Za-z0-9_-]+)"|target="([^"]+)"|rel="([A-Za-z0-9_-]+)")\})?/', static function (array $match) use (&$links): string {
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

        $source = preg_replace('/<(?=\d)/', "\u{E013}", $source);
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

        $source = str_replace(['\\*', '\\[', '\\]', '\\+', '\\-', '_,_'], ["\u{E018}", "\u{E027}", "\u{E028}", "\u{E029}", "\u{E030}", "\u{E032}"], $source);
        $source = preg_replace('/(?<=[\p{L}\p{N}_])_\._/u', "\u{E031}", $source);
        if (null === $source) {
            return null;
        }

        $source = preg_replace('/(?<=\d)\*(?=\/\d)/', "\u{E018}", $source);
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

        $literalTilde = str_contains($source, '~');
        if ($literalTilde) {
            if (str_contains($source, '~~') || str_contains($source, "\u{E002}")) {
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
                $content = preg_replace('/~([^\s~<>]+)~/', '<del>$1</del>', $cell[1]);

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

            $html = str_replace(
                ['<table>', '<thead>', '<tbody>', '<tr>', '</th>', '</td>', '</tr>', '</thead>', '</tbody>', '</table>'],
                ["<table>\n", "<thead>\n", "<tbody>\n", "<tr>\n", "</th>\n", "</td>\n", "</tr>\n", "</thead>\n", "</tbody>\n", "</table>\n"],
                $html,
            );
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
                        $url = preg_replace_callback('/[^\x21-\x7E]/u', static fn (array $character): string => rawurlencode($character[0]), $link['url']);

                        return $this->linkProvider?->renderLink($match[2], $url ?? $link['url'], $link['attributes'], true) ?? $match[0];
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

        $html = str_replace("\u{E009}", '_', $html);
        $html = str_replace("\u{E013}", '&lt;', $html);
        $html = str_replace(["\u{E018}", "\u{E019}"], ['*', '**'], $html);
        $html = str_replace(["\u{E027}", "\u{E028}"], ['[', ']'], $html);
        $html = str_replace(["\u{E029}", "\u{E030}"], ['+', '-'], $html);
        $html = str_replace(["\u{E031}", "\u{E032}"], ['_._', '_,_'], $html);
        $html = str_replace("\u{E033}\n", "<br />\n", $html);
        $html = str_replace("\u{E020}", '_', $html);
        $html = str_replace("\u{E026}", '.', $html);
        $html = str_replace(["\u{E010}", "\u{E011}"], ['[', ']'], $html);
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
    private function renderNestedList(array $items, int &$position, int $indent): ?string
    {
        $html = "<ul>\n";
        while (isset($items[$position]) && $items[$position]['indent'] === $indent) {
            $content = $this->render($items[$position]['text']);
            if (null === $content || ! str_starts_with($content, '<p>') || ! str_ends_with($content, "</p>\n")) {
                return null;
            }

            $html .= '<li>'.substr($content, 3, -5);
            ++$position;
            if (isset($items[$position]) && $items[$position]['indent'] > $indent) {
                $nested = $this->renderNestedList($items, $position, $items[$position]['indent']);
                if (null === $nested) {
                    return null;
                }

                $html .= "\n".$nested;
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

        if (str_contains($source, '***') || str_contains($source, '___') || 1 === preg_match("/`[^`]*'[^`]*`/", $source)) {
            return false;
        }

        $withoutLinks = $source;
        if (str_contains($source, '[')) {
            $linkPattern = '/\[[^][]+\]\((<[^>\r\n]+>|[^()\r\n]*)\)/';
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

        if (1 === preg_match('/(?:date\(|\+33[ .-]?[1-9](?:[ .-]?\d{2}){4}|(?<!\d)0[1-9](?:[ .-]?\d{2}){4}(?!\d)|^[-=]{3,}\s*$)/i', $source)) {
            return false;
        }

        return $heading || 0 === preg_match('/^(?:\d+[.)]|[-+*])\s/', $source);
    }
}
