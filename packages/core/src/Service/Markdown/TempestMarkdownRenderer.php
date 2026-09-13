<?php

declare(strict_types=1);

namespace Pushword\Core\Service\Markdown;

use Pushword\Core\Component\EntityFilter\Filter\Date;
use Pushword\Core\Service\LinkProvider;
use Pushword\Core\Site\SiteRegistry;
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

    public function __construct(private ?LinkProvider $linkProvider = null, private ?SiteRegistry $apps = null, private ?Twig $twig = null)
    {
        $this->markdown = new Markdown(null);
        $this->markdownWithoutFrontMatter = new Markdown(null)->removeRules(FrontMatterRule::class);
        $this->dateFilter = null === $apps ? null : new Date($apps);
    }

    public function render(string $source): ?string
    {
        if (str_starts_with($source, '> [!')) {
            return $this->renderNotice($source);
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
        $table = 1 === preg_match('/^\|[^\n]+\|\n\|[\s|:-]+\|\n(?:\|[^\n]+\|\n?)+$/D', $source)
            && 1 === preg_match('/^\|(?:\s*[^|\s][^|]*\|)+\n/', $source)
            && false === strpbrk($source, '<>&~[]{}');
        $attribute = null;
        if (null === $listTag && 1 === preg_match('/^\{(?:id=([A-Za-z0-9_-]+)(?: \.([A-Za-z0-9_-]+))?|\.([A-Za-z0-9_-]+))\}\n([^\r\n]+)$/D', $source, $attributes)) {
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
            if (null === $this->dateFilter || str_contains($source, '`') || str_contains($source, '[')) {
                return null;
            }

            try {
                $source = $this->dateFilter->convertDateShortCode($source, $this->apps?->get()->locale);
            } catch (Throwable) {
                return null;
            }
        }

        $contacts = [];
        if (null !== $this->linkProvider && ! str_contains($source, '[') && ! str_contains($source, '`') && ! str_contains($source, '<') && ! str_contains($source, "\u{E000}")) {
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

        $links = [];
        if (str_contains($source, '){') || str_contains($source, '#[')) {
            if (str_contains($source, '`') || (str_contains($source, '#[') && null === $this->linkProvider)) {
                return null;
            }

            $source = preg_replace_callback('/(#?)(\[[^][]+\]\(([^()\r\n]*)\))(?:\{(?:\.([A-Za-z0-9_-]+)|class="([A-Za-z0-9_-]+)"|target="([^"]+)"|rel="([A-Za-z0-9_-]+)")\})?/', static function (array $match) use (&$links): string {
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

                $links[] = ['obfuscated' => '#' === $match[1], 'url' => $match[3], 'attributes' => $attributes];

                return $match[2];
            }, $source);
            if (null === $source) {
                return null;
            }
        }

        $literalTilde = str_contains($source, '~');
        if ($literalTilde) {
            if (str_contains($source, '~~') || str_contains($source, "\u{E002}")) {
                return null;
            }

            $source = str_replace('~', "\u{E002}", $source);
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

                foreach (explode("\n", rtrim($content, "\n")) as $line) {
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
                        return $this->linkProvider?->renderLink($match[2], $link['url'], $link['attributes'], true) ?? $match[0];
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

            $html = str_replace(
                ['<'.$listTag.'>', '</li><li>', '</li></'.$listTag.'>'],
                ['<'.$listTag.">\n", "</li>\n<li>", "</li>\n</".$listTag.'>'],
                $html,
            );
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
                $parts[$index] = str_replace('"', '&quot;', $part);
            }
        }

        $html = implode('', $parts);
        foreach ($contacts as $index => $contact) {
            $html = str_replace("\u{E000}".$index."\u{E001}", $contact, $html);
        }

        if ($literalTilde) {
            $html = str_replace("\u{E002}", '~', $html);
        }

        return rtrim($html)."\n";
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
        $listBlocks = 0;
        foreach (preg_split('/\n[ \t]*\n/', trim(implode("\n", $body), "\n")) ?: [] as $block) {
            if (str_starts_with($block, '- ') || 1 === preg_match('/^[0-9]+\. /', $block)) {
                ++$listBlocks;
                if ($listBlocks > 1) {
                    return null;
                }
            }

            $html = $this->render($block);
            if (null === $html) {
                return null;
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

        if (false !== strpbrk($source, "\r\n\t{}<>\\~&@") || 1 === preg_match('/[\x00-\x1F\x7F]/', $source)) {
            return false;
        }

        if (($heading && str_contains($source, '#')) || (! $heading && (str_starts_with($source, '#') || str_contains($source, '#['))) || str_contains($source, '![')) {
            return false;
        }

        if (str_contains($source, '***') || str_contains($source, '___') || 1 === preg_match('/[\p{L}\p{N}]_+[^_\r\n]*_+/u', $source) || 1 === preg_match("/`[^`]*'[^`]*`/", $source)) {
            return false;
        }

        if (str_contains($source, '[')) {
            $linkPattern = '/\[[^][]+\]\(([^()\r\n]*)\)/';
            preg_match_all($linkPattern, $source, $links);
            foreach ($links[1] as $destination) {
                if (1 === preg_match('/[^\x21-\x7E]|[\'\"]/', $destination)) {
                    return false;
                }
            }

            $withoutLinks = preg_replace($linkPattern, '', $source);
            if (null === $withoutLinks || false !== strpbrk($withoutLinks, '[]')) {
                return false;
            }
        }

        if (1 === preg_match('/(?:date\(|https?:\/\/\S*_\S*|\+33[ .-]?[1-9](?:[ .-]?\d{2}){4}|(?<!\d)0[1-9](?:[ .-]?\d{2}){4}(?!\d)|^[-=]{3,}\s*$)/i', $source)) {
            return false;
        }

        return $heading || 0 === preg_match('/^(?:\d+[.)]|[-+*])\s/', $source);
    }
}
