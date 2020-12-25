<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\Entity\Page;
use Twig\Environment as TwigEnv;

class Twig extends AbstractFilter
{
    public Page $page;

    public TwigEnv $twig;

    public function apply(mixed $propertyValue): string
    {
        return $this->render($this->string($propertyValue));
    }

    protected function render(string $string): string
    {
        if (! str_contains($string, '{')) {
            return $string;
        }

        $templateWrapper = $this->twig->createTemplate($string);

        return $templateWrapper->render(['page' => $this->page]);
    }
}
