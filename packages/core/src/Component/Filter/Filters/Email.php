<?php

namespace Pushword\Core\Component\Filter\Filters;

use Pushword\Core\Twig\EmailTwigTrait;

class Email extends ShortCode
{
    use EmailTwigTrait;

    public function apply($string)
    {
        $string = $this->convertEmail($string);

        return $string;
    }

    public function convertEmail($body)
    {
        $body = $this->convertRawEncodeMail($body);
        $body = $this->convertTwigEncodedMail($body);

        return $body;
    }

    // Todo Move it to a dedicatedShortcode
    public function convertTwigEncodedMail($body)
    {
        $rgx = '/{{\s*e?mail\(((?<![\\\])[\'"])((?:.(?!(?<![\\\])\1))*.?)\1\)\s*}}/iU';
        preg_match_all($rgx, $body, $matches);

        if (! isset($matches[2])) {
            return $body;
        }

        $nbrMatch = \count($matches[0]);
        for ($k = 0; $k < $nbrMatch; ++$k) {
            $body = str_replace($matches[0][$k], $this->renderEncodedMail($this->twig, $matches[2][$k]), $body);
        }

        return $body;
    }

    public function convertRawEncodeMail($body)
    {
        $rgx = '/ ([A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}) /i';

        preg_match_all($rgx, $body, $matches);

        $nbrMatch = \count($matches[0]);
        for ($k = 0; $k < $nbrMatch; ++$k) {
            $body = ' '.str_replace($matches[0][$k], $this->renderEncodedMail($this->twig, $matches[1][$k]), $body).' ';
        }

        return $body;
    }
}
