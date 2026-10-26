<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Model;

use Symfony\Component\Validator\Constraints as Assert;

final class AudioTrack
{
    public const string MEDIA_PATTERN = '/\A[a-z0-9_.-]+\.(?:mp3|wav|m4a|ogg|flac)\z/i';

    public function __construct(
        #[Assert\NotBlank(message: 'repurpose.audio.media')]
        #[Assert\Regex(pattern: self::MEDIA_PATTERN, message: 'repurpose.audio.media')]
        public string $media = '',
        #[Assert\Range(notInRangeMessage: 'repurpose.audio.volume', min: 0, max: 1)]
        public float $volume = 1,
        #[Assert\Range(notInRangeMessage: 'repurpose.audio.fade', min: 0, max: 30)]
        public float $fadeIn = 0,
        #[Assert\Range(notInRangeMessage: 'repurpose.audio.fade', min: 0, max: 30)]
        public float $fadeOut = 0,
    ) {
    }
}
