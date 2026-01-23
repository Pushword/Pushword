<?php

namespace Pushword\Core\Utils;

use Exception;

class HtmlBeautifer
{
    public static function removeHtmlComments(string $content): string
    {
        return preg_replace('/<!--(.|\s)*?-->/', '', $content) ?? throw new Exception();
    }

    public static function punctuationBeautifer(string $text): string
    {
        $text = preg_replace('# ([\!\?\:;])([^a-zA-Z]|$)#', '&nbsp;$1$2', $text) ?? throw new Exception();
        // avoid to catch tailwind selector inside ""

        return str_replace(
            ['« ', ' »', '&laquo; ', ' &raquo;'],
            ['«&nbsp;', '&nbsp;»', '&laquo;&nbsp;', '&nbsp;&raquo;'],
            $text
        );
    }
}
