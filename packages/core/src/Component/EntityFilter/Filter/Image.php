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
        if (false === preg_match_all('/(?:!\[(.*?)\]\((.*?)\))/', $body, $matches)) {
            return $body;
        }

        $nbrMatch = \count($matches[0]);
        for ($k = 0; $k < $nbrMatch; ++$k) {
            $renderImg = '<div>'.$this->twig->render(
                $this->app->getView('/component/image_inline.html.twig'),
                [
                    // "image_wrapper_class" : "mimg",'
                    'image_src' => $matches[2][$k],
                    'image_alt' => htmlspecialchars($matches[1][$k]),
                ]
            ).'</div>';
            $body = str_replace($matches[0][$k], $renderImg, $body);
        }

        return $body;
    }
}
