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
        return str_replace(
            [' ;', ' :', ' ?', ' !', '« ', ' »', '&laquo; ', ' &raquo;'],
            ['&nbsp;;', '&nbsp;:', '&nbsp;?', '&nbsp;!', '«&nbsp;', '&nbsp;»', '&laquo;&nbsp;', '&nbsp;&raquo;'],
            $text
        );
    }
}
