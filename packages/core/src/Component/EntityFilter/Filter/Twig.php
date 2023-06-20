<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\AutowiringTrait\RequiredEntityTrait;
use Pushword\Core\AutowiringTrait\RequiredTwigTrait;
use Pushword\Core\Entity\PageInterface;

class Twig extends AbstractFilter
{
    use RequiredEntityTrait;
    use RequiredTwigTrait;

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

        return $templateWrapper->render($this->entity instanceof PageInterface ? ['page' => $this->entity] : []);
    }
}
