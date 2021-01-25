<?php

namespace Pushword\Core\Utils;

use Michelf\MarkdownExtra;

class MarkdownParser extends MarkdownExtra
{
    public function __construct()
    {
        parent::__construct();

        unset($this->block_gamut['doCodeBlocks']);  // break everything witht twig includes
    }

    /**
     * Simplify detab.
     */
    public function detab($text)
    {
        return str_replace("\t", str_repeat(' ', $this->tab_width), $text);
    }

    public function _initDetab()
    {
        return;
    }
}
