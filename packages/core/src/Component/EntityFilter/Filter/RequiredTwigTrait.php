<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Twig\Environment as Twig;

trait RequiredTwigTrait
{
    private Twig $twig;

    public function setTwig(Twig $twig): void
    {
        $this->twig = $twig;
    }

    public function getTwig(): Twig
    {
        return $this->twig;
    }
}
