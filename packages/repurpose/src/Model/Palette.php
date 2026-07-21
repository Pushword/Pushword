<?php

namespace Pushword\Repurpose\Model;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * A slide (or deck) colour override. Every field is optional: an omitted colour
 * falls back to the deck palette, then to the site's own `css_var:*` tokens.
 */
class Palette
{
    private const string HEX = '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/';

    public function __construct(
        #[Assert\Regex(pattern: self::HEX, message: 'repurpose.palette.color.invalid')]
        public ?string $bg = null,
        #[Assert\Regex(pattern: self::HEX, message: 'repurpose.palette.color.invalid')]
        public ?string $text = null,
        #[Assert\Regex(pattern: self::HEX, message: 'repurpose.palette.color.invalid')]
        public ?string $accent = null,
    ) {
    }

    public function isEmpty(): bool
    {
        return null === $this->bg && null === $this->text && null === $this->accent;
    }
}
