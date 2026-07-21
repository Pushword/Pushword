<?php

namespace Pushword\Repurpose\Model;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * The slide-counter chrome ("1/7", dots, …). Deck-level; a `none` style hides it.
 */
class Counter
{
    public const array STYLES = ['none', 'dots', 'fraction', 'bar'];

    public const array ALIGNS = ['left', 'center', 'right'];

    public function __construct(
        #[Assert\Choice(choices: self::STYLES, message: 'repurpose.counter.style.invalid')]
        public string $style = 'fraction',
        #[Assert\Choice(choices: self::ALIGNS, message: 'repurpose.counter.align.invalid')]
        public string $align = 'right',
    ) {
    }
}
