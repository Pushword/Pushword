<?php

namespace Pushword\Repurpose\Model;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * A freely positioned text box drawn over a slide, after the layout stack. All
 * coordinates are fractions of the frame (like an image's focal point), so a
 * spec survives being retargeted to another network's format. The stated size is
 * honoured unless the text would overflow the frame bottom, in which case it
 * shrinks to fit — free placement never escapes the no-overflow guarantee.
 */
class FreeText
{
    public const array ALIGNS = ['left', 'center', 'right'];

    public const array FONTS = ['body', 'heading'];

    public function __construct(
        #[Assert\NotBlank(message: 'repurpose.text.content.empty')]
        public string $content = '',
        /** Left edge of the box, as a fraction of the frame width. */
        #[Assert\Range(notInRangeMessage: 'repurpose.text.position.range', min: 0, max: 1)]
        public float $x = 0.08,
        /** Top edge of the box, as a fraction of the frame height. */
        #[Assert\Range(notInRangeMessage: 'repurpose.text.position.range', min: 0, max: 1)]
        public float $y = 0.08,
        /** Wrap width of the box, as a fraction of the frame width. */
        #[Assert\Range(notInRangeMessage: 'repurpose.text.width.range', min: 0.05, max: 1)]
        public float $width = 0.84,
        /** Font size as a fraction of the frame width (0.05 ≈ 54px on a 1080px slide). */
        #[Assert\Range(notInRangeMessage: 'repurpose.text.size.range', min: 0.01, max: 0.3)]
        public float $size = 0.05,
        #[Assert\Choice(choices: self::ALIGNS, message: 'repurpose.text.align.invalid')]
        public string $align = 'left',
        #[Assert\Choice(choices: self::FONTS, message: 'repurpose.text.font.invalid')]
        public string $font = 'body',
        /** Overrides the slide's text colour; null inherits it. */
        #[Assert\Regex(pattern: Palette::HEX, message: 'repurpose.palette.color.invalid')]
        public ?string $color = null,
        /** Paints a rounded marker behind each line; null draws none. */
        #[Assert\Regex(pattern: Palette::HEX, message: 'repurpose.palette.color.invalid')]
        public ?string $highlight = null,
    ) {
    }

    /**
     * The box must stay inside the frame: a box starting at x with this width
     * would otherwise run off the right edge, which the renderer cannot honour.
     */
    #[Assert\Callback]
    public function validateBox(ExecutionContextInterface $context): void
    {
        if ($this->x + $this->width > 1.0) {
            $context->buildViolation('repurpose.text.box.tooWide')
                ->atPath('width')
                ->addViolation();
        }
    }
}
