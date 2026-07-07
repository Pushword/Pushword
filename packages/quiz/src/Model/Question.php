<?php

namespace Pushword\Quiz\Model;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * A quiz question. It carries an optional illustration: either a `media` image,
 * or a `video` whose poster is that same `media` image. It has at least two
 * answers and an explanation revealed once answered.
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
        public ?string $alt = null,
        public ?string $explanation = null
    ) {
    }

    // The "at least one correct answer" rule is mode-dependent (it must not apply
    // to a personality test), so it lives in {@see Quiz::validateStructure()} which
    // knows the mode — not here.
    #[Assert\Callback]
    public function validateVideo(ExecutionContextInterface $context): void
    {
        if (null === $this->video || '' === $this->video) {
            return;
        }

        // The video is rendered with a poster, and that poster is the `media`
        // image — so an illustrated video must carry one.
        if (null === $this->media || '' === $this->media) {
            $context->buildViolation('quiz.question.video.posterRequired')
                ->atPath('media')
                ->addViolation();
        }

        // A video has no stored Media to fall back on, so its alt text is mandatory.
        if (null === $this->alt || '' === $this->alt) {
            $context->buildViolation('quiz.question.video.altRequired')
                ->atPath('alt')
                ->addViolation();
        }
    }
}
