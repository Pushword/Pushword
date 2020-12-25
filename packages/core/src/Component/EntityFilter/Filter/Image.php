<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\Component\App\AppConfig;

use function Safe\preg_match_all;

use Twig\Environment;

class Image extends AbstractFilter
{
    public AppConfig $app;

    public Environment $twig;

    public function apply(mixed $propertyValue): string
    {
        return $this->convertMarkdownImage($this->string($propertyValue));
    }

    /**
     * @psalm-suppress all
     */
    public function convertMarkdownImage(string $body): string
    {
        preg_match_all('/(?:!\[(.*?)\]\((.*?)\))/', $body, $matches);
        if (! isset($matches[1])) {
            return $body;
        }

        if (! isset($matches[0])) {
            return $body;
        }

        $nbrMatch = is_countable($matches[0]) ? \count($matches[0]) : 0;
        for ($k = 0; $k < $nbrMatch; ++$k) {
            $renderImg = '<div>'.$this->twig->render(
                $this->app->getView('/component/image_inline.html.twig'),
                [
                    // "image_wrapper_class" : "mimg",'
                    'image_src' => $matches[2][$k],
                    'image_alt' => htmlspecialchars((string) $matches[1][$k]),
                ]
            ).'</div>';
            $body = str_replace($matches[0][$k], $renderImg, $body);
        }

        return $body;
    }
}
