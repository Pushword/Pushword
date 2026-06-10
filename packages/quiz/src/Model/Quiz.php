<?php

namespace Pushword\Quiz\Model;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * A whole quiz, as declared inline in a page through `{{ quiz('…json…') }}`.
 * Plain value object: it is hydrated from the decoded JSON ({@see \Pushword\Quiz\Service\QuizFactory})
 * and validated by Symfony Validator — the single source of truth shared by the
 * Twig renderer, the editor lint and the `/api/quiz/validate` endpoint.
 */
class Quiz
{
    /**
     * @param Question[]   $questions
     * @param ResultBand[] $results
     */
    public function __construct(
        public ?string $title = null,
        #[Assert\Count(min: 1, minMessage: 'quiz.questions.min')]
        #[Assert\Valid]
        public array $questions = [],
        #[Assert\Choice(choices: ['immediate', 'end'], message: 'quiz.feedback.invalid')]
        public string $feedback = 'immediate',
        public ?string $difficulty = null,
        #[Assert\Valid]
        public array $results = [],
        public ?string $cta = null
    ) {
    }
}
