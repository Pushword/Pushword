<?php

namespace Pushword\Core\Twig;

trait UnproseTwigTrait
{
    /**
     * Twig filters.
     */
    public function unprose(string $html): string
    {
        $unproseClass = 'not-prose bleed my-6';

        return '<div class="'.$unproseClass.'">'.$html.'</div>';
    }
}
