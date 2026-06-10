<?php

namespace Pushword\Quiz\Model;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * A score band: when the final score (in %) is >= `min`, `msg` is shown.
 */
class ResultBand
{
    public function __construct(
        #[Assert\Range(notInRangeMessage: 'quiz.result.min.range', min: 0, max: 100)]
        public int $min = 0,
        #[Assert\NotBlank(message: 'quiz.result.msg.empty')]
        public string $msg = '',
    ) {
    }
}
