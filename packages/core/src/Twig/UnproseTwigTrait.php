<?php

namespace Pushword\Core\Twig;

trait UnproseTwigTrait
{
    /**
     * Twig filters.
     */
    public function unprose(string $html): string
    {
        $unproseClass = 'not-prose lg:-mx-40 my-6 md:-mx-20';

        return '<div class="'.$unproseClass.'">'.$html.'</div>';
    }
}
