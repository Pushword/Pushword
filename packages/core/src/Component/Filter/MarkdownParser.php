<?php

namespace Pushword\Core\Component\Filter;

use Knp\Bundle\MarkdownBundle\Parser\MarkdownParser as KnpMarkdownParser;

class MarkdownParser extends KnpMarkdownParser
{
    protected $features = [
        'header' => true,
        'list' => true,
        'horizontal_rule' => true,
        'table' => true,
        'foot_note' => true,
        'fenced_code_block' => true,
        'abbreviation' => true,
        'definition_list' => true,
        'inline_link' => true, // [link text](url "optional title")
        'reference_link' => true, // [link text] [id]
        'shortcut_link' => true, // [link text]
        'images' => true,
        'block_quote' => true,
        'code_block' => false, // break everything witht twig includes
        'html_block' => true,
        'auto_link' => true,
        'auto_mailto' => true,
        'entities' => true,
        'no_html' => false,
    ];
}
