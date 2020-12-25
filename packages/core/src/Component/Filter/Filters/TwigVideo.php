<?php

namespace Pushword\Core\Component\Filter\Filters;

use Pushword\Core\Twig\VideoTwigTrait;

class TwigVideo extends ShortCode
{
    use VideoTwigTrait;

    public function apply($string)
    {
        $string = $this->convertTwigVideo($string);

        return $string;
    }

    public function convertTwigVideo($body)
    {
        $rgx = '/{{\s*video\(\s*((?<![\\\])[\'"])((?:.(?!(?<![\\\])\1))*.?)\1\s*'
            .',\s*((?<![\\\])[\'"])((?:.(?!(?<![\\\])\3))*.?)\3\s*'
            .',\s*((?<![\\\])[\'"])((?:.(?!(?<![\\\])\5))*.?)\5\s*\)\s*}}/iU'; // 2, 4, 6
        //echo $rgx; exit;
        preg_match_all($rgx, $body, $matches);

        if (! isset($matches[2])) {
            return $body;
        }

        $nbrMatch = \count($matches[0]);
        for ($k = 0; $k < $nbrMatch; ++$k) {
            $body = str_replace(
                $matches[0][$k],
                $this->renderVideo($matches[2][$k], $matches[4][$k], $matches[6][$k]),
                $body
            );
        }

        return $body;
    }
}
