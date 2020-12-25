<?php

namespace Pushword\Core\Component\Filter\Filters;

class Twig extends ShortCode
{
    public function apply($string)
    {
        $string = $this->render($string);

        return $string;
    }

    protected function render($string)
    {
        if (! $string || false === strpos($string, '{')) {
            return $string;
        }

        $tmpl = $this->twig->createTemplate($string);
        $string = $tmpl->render(['page' => $this->page]);

        return $string;
    }
}
