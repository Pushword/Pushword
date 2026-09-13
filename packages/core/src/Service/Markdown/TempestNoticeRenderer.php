<?php

declare(strict_types=1);

namespace Pushword\Core\Service\Markdown;

use Closure;
use Pushword\Core\Site\SiteRegistry;
use Throwable;
use Twig\Environment as Twig;

final readonly class TempestNoticeRenderer
{
    /** @param Closure(string): ?string $renderMarkdown */
    public function __construct(private ?Twig $twig, private ?SiteRegistry $apps, private Closure $renderMarkdown)
    {
    }

    /** @param array<string, string> $attributes */
    public function render(string $source, array $attributes = []): ?string
    {
        $lines = explode("\n", $source);
        if (null === $this->twig || null === $this->apps || 1 !== preg_match('/^> \[!([a-z][a-z0-9_-]*)\](?: ([^\r\n]*))?$/iD', array_shift($lines), $matches)) {
            return null;
        }

        $title = $matches[2] ?? '';
        if (1 === preg_match('/^(.*?)(?: )?\{([^{}]+)\}$/D', $title, $trailing)) {
            $inlineAttributes = $this->parseAttributes($trailing[2]);
            if (null !== $inlineAttributes) {
                $title = $trailing[1];
                $attributes = array_replace($attributes, $inlineAttributes);
            }
        }

        $title = '' === $title ? ucfirst(strtolower($matches[1])) : $title;

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

            $html = ($this->renderMarkdown)($block);
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
                'title' => $title,
                'content' => implode("\n", $content),
                'id' => $attributes['id'] ?? '',
                'class' => $attributes['class'] ?? '',
                'params' => array_diff_key($attributes, ['id' => '', 'class' => '']),
            ])."\n";
        } catch (Throwable) {
            return null;
        }
    }

    /** @return ?array<string, string> */
    public function parseAttributes(string $literal): ?array
    {
        preg_match_all('/#[A-Za-z0-9_-]+|\.[A-Za-z0-9_-]+|[a-z][a-z0-9_-]*="[^"]*"|id=[A-Za-z0-9_-]+/i', $literal, $matches);
        if (implode(' ', $matches[0]) !== $literal) {
            return null;
        }

        $attributes = [];
        foreach ($matches[0] as $token) {
            if ('#' === $token[0]) {
                $attributes['id'] = substr($token, 1);
            } elseif ('.' === $token[0]) {
                $attributes['class'] = trim(($attributes['class'] ?? '').' '.substr($token, 1));
            } elseif (str_starts_with($token, 'id=')) {
                $attributes['id'] = substr($token, 3);
            } else {
                [$name, $value] = explode('=', $token, 2);
                $attributes[$name] = trim($value, '"');
            }
        }

        return $attributes;
    }
}
