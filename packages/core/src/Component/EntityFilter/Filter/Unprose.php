<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\Twig\ClassTrait;
use Pushword\Core\Twig\UnproseTwigTrait;

/**
 * /!\
 * This Unprose filter work only if the class's div encapsulating the entity's property is
 * `<div class="{{ class(entity, 'prose') }}">`.
 *
 * It's not Feng Shui but it's the simple solution to work with `michelf/markdow <div markdown=1></div>`
 * and Tailwind Typography plugin
 */
class Unprose extends AbstractFilter
{
    use ClassTrait;
    use RequiredAppTrait;
    use RequiredEntityTrait;
    use UnproseTwigTrait;

    /**
     * @return string
     */
    public function apply($string)
    {
        $string = str_replace(
            ['<p>'.$this->encryptTag('</div>').'</p>', '<p>'.$this->encryptTag('<div>').'</p>'],
            ['</div>', '<div class="'.$this->getClass($this->entity, 'prose').'">'],
            $string
        );

        $string = str_replace(
            [$this->encryptTag('</div>'), $this->encryptTag('<div>')],
            ['</div>', '<div class="'.$this->getClass($this->entity, 'prose').'">'],
            $string
        );

        return $string;
    }
}
