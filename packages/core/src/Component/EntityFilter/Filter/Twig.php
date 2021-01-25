<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\Entity\PageInterface;

class Twig extends AbstractFilter
{
    use RequiredEntityTrait;
    use RequiredTwigTrait;

    /**
     * @return string
     */
    public function apply($string)
    {
        $string = $this->render($string);

        return $string;
    }

    protected function render(string $string): string
    {
        if (! $string || false === strpos($string, '{')) {
            return $string;
        }

        $tmpl = $this->twig->createTemplate($string);
        $string = $tmpl->render($this->entity instanceof PageInterface ? ['page' => $this->entity] : []);

        return $string;
    }
}
