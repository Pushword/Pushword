<?php

declare(strict_types=1);

namespace Pushword\Quiz\Model;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * A result band: when the percentage score or assessment value is >= `min`, `msg` is shown.
 */
class ResultBand
{
    public function __construct(
        public float $min = 0,
        #[Assert\NotBlank(message: 'quiz.result.msg.empty')]
        public string $msg = '',
    ) {
    }

    #[Assert\Callback]
    public function validateThreshold(ExecutionContextInterface $context): void
    {
        if (! is_finite($this->min)) {
            $context->buildViolation('quiz.result.min.finite')->atPath('min')->addViolation();
        }
    }
}
