<?php

namespace Pushword\Core\Component\EntityFilter\Filter;

use Pushword\Core\AutowiringTrait\RequiredAppTrait;
use Pushword\Core\AutowiringTrait\RequiredEntityTrait;
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
    public function apply($propertyValue)
    {
        $closeEncryptedTag = $this->encryptTag('div');
        $openEncryptedTag = $this->encryptTag('/div');

        // Remove blank prose added (eg: between to apply unprose ?)
        //$propertyValue = preg_replace('/('.preg_quote($closeEncryptedTag, '/').'\s*'.preg_quote($openEncryptedTag, '/').')/', '', $propertyValue);

        $propertyValue = str_replace(
            [$closeEncryptedTag, $openEncryptedTag],
            ['</div>', '<div class="'.$this->getClass($this->entity, 'prose').'">'],
            $propertyValue
        );

        return $propertyValue;
    }

    /*
        // Fix markdown encapsulate encryptedTag
    $string = str_replace(
            ['<p>'.$closeEncryptedTag.'</p>', $closeEncryptedTag.'</p>', '<p>'.$closeEncryptedTag],
            $closeEncryptedTag,
            $string
        );
        $string = str_replace(
            ['<p>'.$openEncryptedTag.'</p>', '<p>'.$openEncryptedTag, $openEncryptedTag.'</p>'],
            $openEncryptedTag,
            $string
        );
    */
}
