<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Model;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * A heading + body pairing of any two Google Fonts families, for typography the
 * registry does not list. Each family is downloaded once, at the weight of its
 * role, into `repurpose.font_dir`.
 */
final readonly class FontPairing
{
    /** A Google Fonts family name: letters, digits and spaces (it goes into the download URL). */
    public const string FAMILY = '/^[A-Za-z0-9][A-Za-z0-9 ]{0,63}$/';

    public function __construct(
        #[Assert\NotBlank(message: 'repurpose.fontPairing.family.empty')]
        #[Assert\Regex(pattern: self::FAMILY, message: 'repurpose.fontPairing.family.invalid')]
        public string $heading = '',
        #[Assert\NotBlank(message: 'repurpose.fontPairing.family.empty')]
        #[Assert\Regex(pattern: self::FAMILY, message: 'repurpose.fontPairing.family.invalid')]
        public string $body = '',
    ) {
    }
}
