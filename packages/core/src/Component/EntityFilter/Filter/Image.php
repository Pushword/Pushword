<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\AutowiringTrait\RequiredAppTrait;
use Pushword\Core\AutowiringTrait\RequiredTwigTrait;

class Image extends AbstractFilter
{
    use RequiredAppTrait;
    use RequiredTwigTrait;

    public function apply($propertyValue): string
    {
        return $this->convertMarkdownImage(\strval($propertyValue));
    }

    public function convertMarkdownImage(string $body): string
    {
        \Safe\preg_match_all('/(?:!\[(.*?)\]\((.*?)\))/', $body, $matches);

        if (! isset($matches[1])) {
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
