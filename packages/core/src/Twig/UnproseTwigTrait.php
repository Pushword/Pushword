<?php

namespace Pushword\Core\Twig;

trait UnproseTwigTrait
{
    /**
     * Twig filters.
     */
    public function unprose(string $html): string
    {
        $unproseClass = 'not-prose w-screen relative left-[49%] ml-[-50vw]';

        return '<div class="'.$unproseClass.'">'.$html.'</div>';
    }
}
