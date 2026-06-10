<?php

namespace Pushword\Quiz\Model;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * One proposition of a {@see Question}. `correct` marks it as (one of) the
 * expected answer(s); `media`/`alt` let a proposition carry an illustrative image.
 */
class Answer
{
    public function __construct(
        #[Assert\NotBlank(message: 'quiz.answer.text.empty')]
        public string $text = '',
        public bool $correct = false,
        public ?string $media = null,
        public ?string $alt = null,
    ) {
    }
}
