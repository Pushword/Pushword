<?php

namespace Pushword\Core\Component\Filter\Filters;

/**
 * /!\
 * This Unprose filter work only if the class's div encapsulating the page.content.body is
 * `<div class="{{ page.prose|default(apps.get().get('page_prose')|default('prose max-w-none')) }}">`.
 *
 * It's not Feng Shui but it's the simple solution to work with `michelf/markdow <div markdown=1></div>`
 */
class Unprose extends ShortCode
{
    public function apply($string)
    {
        $string = str_replace(
            [self::encrypt('</div>'), self::encrypt('<div>')],
            ['</div>', '<div class="'.$this->getPageProseClass().'">'],
            $string
        );

        return $string;
    }

    /**
     * This function duplicate packages/core/src/templates/page/_content.html.twig:
     *  `<div class="{{ page.prose|default(apps.get().get('page_prose')|default('prose max-w-none')) }}">`.
     */
    private function getPageProseClass(): string
    {
        return null !== $this->page->getCustomProperty('prose') ? $this->page->getCustomProperty('prose')
            : (null !== $this->getApp()->getCustomProperty('page_prose') ? $this->getApp()->getCustomProperty('page_prose')
                : 'prose max-w-none');
    }

    public static function encrypt($tag)
    {
        return sha1($tag.date('Y'));
    }

    /**
     * Twig filters.
     */
    public static function unprose(string $html): string
    {
        return self::encrypt('</div>').str_replace("\n", '', $html).self::encrypt('<div>');
    }
}
