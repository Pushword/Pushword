<?php

namespace Pushword\Quiz\Model;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * A whole quiz, as declared inline in a page through `{{ quiz('…json…') }}`.
 * Plain value object: it is hydrated from the decoded JSON ({@see \Pushword\Quiz\Service\QuizFactory})
 * and validated by Symfony Validator — the single source of truth shared by the
 * Twig renderer, the editor lint and the `/api/quiz/validate` endpoint.
 *
 * A quiz may instead expose several difficulty `levels`: each level is itself a
 * full leaf Quiz (its own questions/results), the root keeps the shared metadata
 * (title, labels) and renders an accessible tab selector. A quiz without `levels`
 * renders exactly as before.
 */
class Quiz
{
    /**
     * @param Question[]            $questions
     * @param ResultBand[]          $results
     * @param array<string, string> $labels    overrides for the locale-defaulted UI words: question, questions, explanation, score, better, level, nextLevel
     * @param Quiz[]                $levels    difficulty levels (each a leaf quiz); empty for a single-level quiz
     */
    public function __construct(
        public ?string $title = null,
        #[Assert\Valid]
        public array $questions = [],
        #[Assert\Choice(choices: ['immediate', 'end'], message: 'quiz.feedback.invalid')]
        public string $feedback = 'immediate',
        public ?string $difficulty = null,
        #[Assert\Valid]
        public array $results = [],
        public ?string $cta = null,
        public ?string $ctaTitle = null,
        #[Assert\Choice(choices: ['', 'A', 'a', '1'], message: 'quiz.numbering.invalid')]
        public string $numbering = '',
        public array $labels = [],
        #[Assert\Range(notInRangeMessage: 'quiz.pass.range', min: 0, max: 100)]
        public ?int $pass = null,
        public ?string $label = null,
        #[Assert\Valid]
        public array $levels = [],
    ) {
    }

    /**
     * A quiz must carry questions, unless it delegates them to difficulty levels.
     * (Each level, being a leaf quiz with no levels of its own, hits this same rule
     * and so is required to hold its own questions.).
     */
    #[Assert\Callback]
    public function validateStructure(ExecutionContextInterface $context): void
    {
        if ([] === $this->levels && \count($this->questions) < 1) {
            $context->buildViolation('quiz.questions.min')
                ->atPath('questions')
                ->addViolation();
        }
    }
}
