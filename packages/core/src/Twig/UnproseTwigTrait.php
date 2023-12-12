<?php

namespace Pushword\Core\Twig;

use Twig\Environment as Twig;

trait UnproseTwigTrait
{
    /**
     * Twig filters.
     *
     * @psalm-suppress UnnecessaryVarAnnotation
     * @psalm-suppress InternalMethod
     */
    public function unprose(string $html): string
    {
        /** @var Twig */
        $twig = $this->twig;
        $unproseClass = $twig->getGlobals()['unprose'] ?? '';

        if ('' === $unproseClass) {
            return $html;
        }

        return '<div class="'.$unproseClass.'">'.$html.'</div>';
    }
}
