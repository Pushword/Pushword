<?php

namespace Pushword\Core\Utils;

class HtmlBeautifer
{
    public static function removeHtmlComments(string $content)
    {
        return preg_replace('/<!--(.|\s)*?-->/', '', $content);
    }

    public static function punctuationBeautifer($text)
    {
        $text = preg_replace('# ([\!\?\:;])([^a-zA-Z]|$)#', '&nbsp;$1$2', $text);
        // avoid to catch tailwind selector inside ""

        return str_replace(
            ['« ', ' »', '&laquo; ', ' &raquo;'],
            ['«&nbsp;', '&nbsp;»', '&laquo;&nbsp;', '&nbsp;&raquo;'],
            $text
        );
    }
}
