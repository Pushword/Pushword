<?php

namespace Pushword\Core\AutowiringTrait;

use Twig\Environment as Twig;

trait RequiredTwigTrait
{
    private Twig $twig;

    public function setTwig(Twig $twig): self
    {
        $this->twig = $twig;

        return $this;
    }

    public function getTwig(): Twig
    {
        return $this->twig;
    }
}
