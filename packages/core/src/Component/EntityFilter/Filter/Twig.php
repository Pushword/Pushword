<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\Component\EntityFilter\Attribute\AsFilter;
use Pushword\Core\Component\EntityFilter\Manager;
use Pushword\Core\Entity\Page;
use Twig\Environment;

#[AsFilter]
class Twig implements FilterInterface
{
    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    public function apply(mixed $propertyValue, Page $page, Manager $manager, string $property = ''): mixed
    {
        assert(is_scalar($propertyValue));

        return $this->render((string) $propertyValue, $page);
    }

    protected function render(string $string, Page $page): string
    {
        if (! str_contains($string, '{')) {
            return $string;
        }

        $templateWrapper = $this->twig->createTemplate($string);

        return $templateWrapper->render(['page' => $page]);
    }
}
