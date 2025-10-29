<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\Component\App\AppConfig;
use Twig\Environment;

class Image extends AbstractFilter
{
    public AppConfig $app;

    public Environment $twig;

    public function apply(mixed $propertyValue): string
    {
        return $this->convertMarkdownImage($this->string($propertyValue));
    }

    public function convertMarkdownImage(string $body): string
    {
        if (false === preg_match_all('/(?:(?:!\[(?<alt>.*)\]\((?<src>.*)\))|(?<hash>#?)\[!\[(?<alt1>.*)\]\((?<src1>.*)\)]\((?<href>.*)\)(?<target>{target="_blank"})?)/', $body, $matches)) {
            return $body;
        }

        $nbrMatch = \count($matches[0]);
        for ($k = 0; $k < $nbrMatch; ++$k) {
            // dump($matches);
            $renderImg = $this->twig->render(
                $this->app->getView('/component/image_inline.html.twig'),
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
