<?php

namespace Pushword\Core\Service\Markdown;

use Pushword\Core\Component\App\AppPool;
use Twig\Environment;

class MarkdownParserImage
{
    public function __construct(
        private Environment $twig,
        private AppPool $apps,
    ) {
    }

    public function parse(string $body): string
    {
        if (false === preg_match_all('/(?:(?:!\[(?<alt>.*)\]\((?<src>.*)\))|(?<hash>#?)\[!\[(?<alt1>.*)\]\((?<src1>.*)\)]\((?<href>.*)\)(?<target>{target="_blank"})?)/', $body, $matches)) {
            return $body;
        }

        $nbrMatch = \count($matches[0]);
        for ($k = 0; $k < $nbrMatch; ++$k) {
            // dump($matches);
            $renderImg = $this->twig->render(
                $this->apps->get()->getView('/component/image_inline.html.twig'),
                [
                    // "image_wrapper_class" : "mimg",'
                    'image_src' => $matches['src'][$k] ?: $matches['src1'][$k],
                    'image_alt' => htmlspecialchars($matches['alt'][$k] ?: $matches['alt1'][$k]),
                    'image_link' => $matches['href'][$k] ?? null,
                    'image_link_attr' => '' !== $matches['target'][$k] ? ['target' => '_blank'] : null,
                    'image_link_obf' => '' !== $matches['hash'][$k],
                ]
            );
            $body = str_replace($matches[0][$k], $renderImg, $body);
        }

        return $body;
    }
}
