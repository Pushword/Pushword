<?php

namespace Pushword\Quiz\Model;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * One proposition of a {@see Question}. In a knowledge quiz `correct` marks it as
 * (one of) the expected answer(s); in a personality test (`mode: profile`) it
 * instead carries `weights` — a map of profile key => points it contributes.
 * `media`/`alt` let a proposition carry an illustrative image.
 */
class Answer
{
    /**
     * @param array<string, int> $weights profile key => points (personality mode); empty in a knowledge quiz
     */
    public function __construct(
        #[Assert\NotBlank(message: 'quiz.answer.text.empty')]
        public string $text = '',
        public bool $correct = false,
        public ?string $media = null,
        public ?string $alt = null,
        public array $weights = [],
    ) {
    }
}
