<?php

namespace Pushword\Quiz\Model;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * A quiz question. It carries an optional illustration (`media` image OR `video`
 * + `poster`), at least two answers, and an explanation revealed once answered.
 */
class Question
{
    /**
     * @param Answer[] $answers
     */
    public function __construct(
        #[Assert\NotBlank(message: 'quiz.question.text.empty')]
        public string $q = '',
        #[Assert\Count(min: 2, minMessage: 'quiz.question.answers.min')]
        #[Assert\Valid]
        public array $answers = [],
        public ?string $media = null,
        public ?string $video = null,
        public ?string $poster = null,
        public ?string $alt = null,
        public ?string $explanation = null
    ) {
    }

    #[Assert\Callback]
    public function validateAnswers(ExecutionContextInterface $context): void
    {
        $correct = array_filter($this->answers, static fn (Answer $answer): bool => $answer->correct);
        if ([] === $correct) {
            $context->buildViolation('quiz.question.correct.min')
                ->atPath('answers')
                ->addViolation();
        }

        // A video has no stored Media to fall back on, so its alt text is mandatory.
        if (null !== $this->video && '' !== $this->video && (null === $this->alt || '' === $this->alt)) {
            $context->buildViolation('quiz.question.video.altRequired')
                ->atPath('alt')
                ->addViolation();
        }
    }
}
