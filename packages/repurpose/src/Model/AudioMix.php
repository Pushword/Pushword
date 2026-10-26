<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Model;

use Symfony\Component\Validator\Constraints as Assert;

final class AudioMix
{
    public function __construct(
        #[Assert\Valid]
        public ?AudioTrack $voice = null,
        #[Assert\Valid]
        public ?AudioTrack $music = null,
        public bool $ducking = true,
    ) {
    }
}
