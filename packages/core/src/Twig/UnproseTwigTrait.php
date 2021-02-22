<?php

namespace Pushword\Core\Twig;

trait UnproseTwigTrait
{
    public function encryptTag(string $tag): string
    {
        return '<'.$tag.' '.sha1($tag.date('Y')).'>';
    }

    /**
     * Twig filters.
     */
    public function unprose(string $html): string
    {
        return $this->encryptTag('div').str_replace("\n", '', $html).$this->encryptTag('/div');
    }
}
