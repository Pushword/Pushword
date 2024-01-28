<?php

namespace Pushword\StaticGenerator\Generator;

use WyriHaximus\HtmlCompress\Factory as HtmlCompressorFactory;

class HtmlCompressor
{
    public static function compress(string $html): string
    {
        // TODO wait for https://github.com/voku/simple_html_dom/pull/106 be merged to restor html compressor with PHP 8.3
        // and remove package https://github.com/devteam-emroc/simple_html_dom in base and core
        $html = preg_replace('/<!--(.*?)-->/s', '', $html);

        return $html; // return HtmlCompressorFactory::construct()->compress($html);
    }
}
