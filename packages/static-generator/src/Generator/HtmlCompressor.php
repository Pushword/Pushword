<?php

namespace Pushword\StaticGenerator\Generator;

// use WyriHaximus\HtmlCompress\Factory\HtmlCompressor as wHtmlCompressor;

class HtmlCompressor
{
    public static function compress(string $html): string
    {
        // TODO wait for https://github.com/voku/simple_html_dom/pull/106 be merged to restor html compressor with PHP 8.3
        // restore dependency wyrihaximus/html-compress
        // $html = $this->parser->compress($html);
        // return wHtmlCompressor::construct()->compress($html);
        return $html;
    }
}
