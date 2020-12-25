<?php

namespace Pushword\Core\Component\Filter\Filters;

class Image extends ShortCode
{
    public function apply($string)
    {
        $string = $this->convertMarkdownImage($string);

        return $string;
    }

    public function convertMarkdownImage($body)
    {
        preg_match_all('/(?:!\[(.*?)\]\((.*?)\))/', $body, $matches);

        if (! isset($matches[1])) {
            return $body;
        }

        $nbrMatch = \count($matches[0]);
        for ($k = 0; $k < $nbrMatch; ++$k) {
            $renderImg = $this->twig->render(
                $this->app->getView('/component/inline_image.html.twig', $this->twig),
                [
                    //"image_wrapper_class" : "mimg",'
                    'image_src' => $matches[2][$k],
                    'image_alt' => htmlspecialchars($matches[1][$k]),
                ]
            );
            $body = str_replace($matches[0][$k], $renderImg, $body);
        }

        return $body;
    }
}
