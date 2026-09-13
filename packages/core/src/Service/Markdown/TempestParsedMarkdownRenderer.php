<?php

declare(strict_types=1);

namespace Pushword\Core\Service\Markdown;

use Closure;
use Pushword\Core\Component\EntityFilter\Filter\Date;
use Pushword\Core\Service\LinkProvider;
use Pushword\Core\Site\SiteRegistry;
use Tempest\Markdown\Markdown;
use Tempest\Markdown\Rules\FrontMatterRule;
use Throwable;

/** Adapts Tempest parsing while preserving Pushword links and literal content. */
final readonly class TempestParsedMarkdownRenderer
{
    private Markdown $markdownWithoutFrontMatter;

    private ?Date $dateFilter;

    /** @param Closure(string): ?string $renderMarkdown */
    public function __construct(private Markdown $markdown, private ?LinkProvider $linkProvider, private ?SiteRegistry $apps, private TempestImageRestorer $images, private Closure $renderMarkdown)
    {
        $this->markdownWithoutFrontMatter = new Markdown(null)->removeRules(FrontMatterRule::class);
        $this->dateFilter = null === $apps ? null : new Date($apps);
    }

    /** @param list<array{src: string, alt: string, linked: bool}> $inlineImages */
    public function render(string $source, array $inlineImages): ?string
    {
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

        // A soft wrap inside a link label is still one link. Keep its newline in
        // the rendered label while the line-by-line compatibility checks run.
        $source = preg_replace_callback('/\[([^\[\]]*\n[^\[\]]*)\]\(([^()\s\r\n]+)\)/', static fn (array $match): string => '['.preg_replace('/\n[ \t]*/', "\u{E063}", $match[1]).']('.$match[2].')', $source);
        if (null === $source) {
            return null;
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

        $inlineCode = [];
        if (str_contains($source, '`') && (str_contains($source, 'date(') || str_contains($source, '@') || 1 === preg_match('/\b0[1-9](?:[ .-]?\d{2}){4}\b/', $source))) {
            $source = preg_replace_callback('/`+[^`\r\n]*`+/', static function (array $match) use (&$inlineCode): string {
                $inlineCode[] = $match[0];

                return "\u{E058}".(\count($inlineCode) - 1)."\u{E059}";
            }, $source);
            if (null === $source) {
                return null;
            }
        }

        if (str_contains($source, 'date(')) {
            if (null === $this->dateFilter) {
                return null;
            }

            try {
                $source = $this->dateFilter->convertDateShortCode($source, $this->apps?->get()->locale);
            } catch (Throwable) {
                return null;
            }
        }

        $mailtoLinks = [];
        if (str_contains($source, '](mailto:')) {
            $source = preg_replace_callback('/\[[^][\r\n]+\]\(mailto:[^()\r\n]+\)/', static function (array $match) use (&$mailtoLinks): string {
                $mailtoLinks[] = $match[0];

                return "\u{E060}".(\count($mailtoLinks) - 1)."\u{E061}";
            }, $source);
            if (null === $source) {
                return null;
            }
        }

        $tripleEmphasis = [];
        $source = preg_replace_callback('/\*{3}([^*\[\]`\n]+)\*{3}/', function (array $match) use (&$tripleEmphasis): string {
            $inner = ($this->renderMarkdown)($match[1]);
            if (null === $inner || ! str_starts_with($inner, '<p>') || ! str_ends_with($inner, "</p>\n")) {
                return $match[0];
            }

            $tripleEmphasis[] = '<strong><em>'.substr($inner, 3, -5).'</em></strong>';

            return "\u{E058}".(\count($tripleEmphasis) - 1)."\u{E059}";
        }, $source);
        if (null === $source) {
            return null;
        }

        $strongCode = [];
        if (! str_contains($source, '```') && ! str_contains($source, '~~~')) {
            $source = preg_replace_callback('/\*\*`([^`\n]+)`\*\*/', static function (array $match) use (&$strongCode): string {
                $strongCode[] = '<strong><code>'.htmlspecialchars($match[1], \ENT_NOQUOTES | \ENT_SUBSTITUTE).'</code></strong>';

                return "\u{E054}".(\count($strongCode) - 1)."\u{E055}";
            }, $source);
            if (null === $source) {
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

        foreach ($inlineCode as $index => $code) {
            $source = str_replace("\u{E058}".$index."\u{E059}", $code, $source);
        }

        foreach ($mailtoLinks as $index => $link) {
            $source = str_replace("\u{E060}".$index."\u{E061}", $link, $source);
        }

        foreach ($obfuscatedLinks as $index => $link) {
            $source = str_replace("\u{E037}".$index."\u{E038}", $link, $source);
        }

        $angleLinks = [];
        if (null !== $this->linkProvider && str_contains($source, '](<')) {
            $source = preg_replace_callback('/#\[([^\]\n]+)\]\(<([^<>\n]+)>\)/', function (array $match) use (&$angleLinks): string {
                $label = ($this->renderMarkdown)($match[1]);
                if (null === $label || ! str_starts_with($label, '<p>') || ! str_ends_with($label, "</p>\n")) {
                    return $match[0];
                }

                $angleLinks[] = $this->linkProvider->renderLink(substr($label, 3, -5), $match[2], [], true);

                return "\u{E050}".(\count($angleLinks) - 1)."\u{E051}";
            }, $source);
            if (null === $source) {
                return null;
            }
        }

        if (str_contains($source, '](<')) {
            return null;
        }

        $emphasisLinks = [];
        if (str_contains($source, '_ [')) {
            $source = preg_replace_callback('/(?<![!#])\[_[^\]\n]+_\]\([^()\s\n]+\)(?!\{)/', function (array $match) use (&$emphasisLinks): string {
                $rendered = ($this->renderMarkdown)($match[0]);
                if (null === $rendered || ! str_starts_with($rendered, '<p><a ') || ! str_ends_with($rendered, "</a></p>\n")) {
                    return $match[0];
                }

                $emphasisLinks[] = substr($rendered, 3, -5);

                return "\u{E056}".(\count($emphasisLinks) - 1)."\u{E057}";
            }, $source);
            if (null === $source) {
                return null;
            }
        }

        $linkTitles = [];
        $source = preg_replace_callback('/(?<!#)\[([^][\r\n]+)\]\(((?:[^()\s<>]|\\\\[()])+) "((?:[^"\\\\\r\n]|\\\\.)*)"\)/u', static function (array $match) use (&$linkTitles): string {
            $linkTitles[] = str_replace('\\"', '"', $match[3]);

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

            $source = preg_replace_callback('/(#?)(\[[^][]+\]\((<[^>\r\n]+>|(?:[^()\r\n]|\\\\[()])*)\))(?:\{(?:\.([A-Za-z0-9_-]+)|class="([A-Za-z0-9_-]+)"|target="([^"]+)"|rel="([A-Za-z0-9_-]+)"|#([A-Za-z0-9_-]+))\})?/', static function (array $match) use (&$links): string {
                $attributes = [];
                $dotClass = $match[4] ?? '';
                $namedClass = $match[5] ?? '';
                if ('' !== $dotClass || '' !== $namedClass) {
                    $attributes['class'] = '' !== $dotClass ? $dotClass : $namedClass;
                } elseif ('' !== ($match[6] ?? '')) {
                    $attributes['target'] = $match[6];
                } elseif ('' !== ($match[7] ?? '')) {
                    $attributes['rel'] = $match[7];
                } elseif ('' !== ($match[8] ?? '')) {
                    $attributes['id'] = $match[8];
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

            $source = preg_replace_callback('/`[^`\n]+`|<[^>]+>/', static function (array $match) use (&$rawTags): string {
                if (str_starts_with($match[0], '`')) {
                    return str_replace(['<', '>'], ["\u{E052}", "\u{E053}"], $match[0]);
                }

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
                $source = preg_replace('/(?m)^ {1,3}(?=\S)/', '', $source);
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
            if (null === $html || (0 === $replacements && 1 !== preg_match('/^<h[1-6]>/', $html))) {
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

        $html = str_replace(["\u{E052}", "\u{E053}"], ['&lt;', '&gt;'], $html);

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

        $html = $this->images->restore($html, $inlineImages);
        if (null === $html) {
            return null;
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
        $html = str_replace("\u{E063}", "\n", $html);
        $html = str_replace(["\u{E047}", "\u{E048}"], ['{', '}'], $html);
        if ($literalLeadingHash) {
            $html = str_replace("\u{E012}", '#', $html);
        }

        foreach ($angleLinks as $index => $link) {
            $html = str_replace("\u{E050}".$index."\u{E051}", $link, $html);
        }

        foreach ($emphasisLinks as $index => $link) {
            $html = str_replace("\u{E056}".$index."\u{E057}", $link, $html);
        }

        foreach ($strongCode as $index => $code) {
            $html = str_replace("\u{E054}".$index."\u{E055}", $code, $html);
        }

        foreach ($tripleEmphasis as $index => $emphasis) {
            $html = str_replace("\u{E058}".$index."\u{E059}", $emphasis, $html);
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

        if (1 === preg_match('/\*{3}\S[^\n]*\*{3}/', $source)) {
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

        $withoutCode = preg_replace('/`+[^`\r\n]*`+/', '', $source) ?? $source;
        if (1 === preg_match('/^[-=]{3,}\s*$/', $withoutCode)) {
            return false;
        }

        if (null === $this->linkProvider && 1 === preg_match('/(?:\+33[ .-]?[1-9](?:[ .-]?\d{2}){4}|(?<!\d)0[1-9](?:[ .-]?\d{2}){4}(?!\d))/', $source)) {
            return false;
        }

        return $heading || 0 === preg_match('/^(?:\d+[.)]|[-+*])\s/', $source);
    }
}
