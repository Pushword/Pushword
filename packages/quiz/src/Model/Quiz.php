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
 *
 * With `mode: profile` the same shape becomes a personality test: answers carry
 * `weights` instead of a `correct` flag, `profiles` replace the score `results`,
 * and the highest-tallied profile is shown as the result card. `levels` are not
 * used in that mode.
 */
class Quiz
{
    /**
     * @param Question[]            $questions
     * @param ResultBand[]          $results
     * @param array<string, string> $labels    overrides for the locale-defaulted UI words: question, questions, explanation, score, better, level, nextLevel
     * @param Quiz[]                $levels    difficulty levels (each a leaf quiz); empty for a single-level quiz
     * @param Profile[]             $profiles  personality-test outcomes; used only in `mode: profile`
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
        #[Assert\Choice(choices: ['quiz', 'profile'], message: 'quiz.mode.invalid')]
        public string $mode = 'quiz',
        #[Assert\Valid]
        public array $profiles = [],
    ) {
    }

    #[Assert\Callback]
    public function validateStructure(ExecutionContextInterface $context): void
    {
        if ('profile' === $this->mode) {
            $this->validateProfileMode($context);

            return;
        }

        // A knowledge quiz must carry questions, unless it delegates them to levels.
        // (Each level, being a leaf quiz with no levels of its own, hits this same
        // rule and so is required to hold its own questions.)
        if ([] === $this->levels && \count($this->questions) < 1) {
            $context->buildViolation('quiz.questions.min')
                ->atPath('questions')
                ->addViolation();
        }

        // Each question needs at least one correct answer (scored mode only).
        foreach ($this->questions as $i => $question) {
            if ([] === array_filter($question->answers, static fn (Answer $answer): bool => $answer->correct)) {
                $context->buildViolation('quiz.question.correct.min')
                    ->atPath('questions['.$i.'].answers')
                    ->addViolation();
            }
        }

        // Loud hint for the common slip: profiles/weights only mean something in
        // `mode: profile`; without it they would be silently ignored.
        if ([] !== $this->profiles || $this->hasWeights()) {
            $context->buildViolation('quiz.mode.hint')
                ->atPath('mode')
                ->addViolation();
        }
    }

    /**
     * A personality test needs questions and at least one profile, and every
     * answer weight must reference a declared profile (a typo would otherwise
     * silently vote for nothing).
     */
    private function validateProfileMode(ExecutionContextInterface $context): void
    {
        if (\count($this->questions) < 1) {
            $context->buildViolation('quiz.questions.min')
                ->atPath('questions')
                ->addViolation();
        }

        if ([] === $this->profiles) {
            $context->buildViolation('quiz.profiles.min')
                ->atPath('profiles')
                ->addViolation();
        }

        $keys = array_map(static fn (Profile $profile): string => $profile->key, $this->profiles);
        foreach ($this->questions as $i => $question) {
            foreach ($question->answers as $j => $answer) {
                foreach (array_keys($answer->weights) as $key) {
                    if (! \in_array($key, $keys, true)) {
                        $context->buildViolation('quiz.profile.weight.unknown')
                            ->setParameter('{key}', $key)
                            ->atPath('questions['.$i.'].answers['.$j.'].weights')
                            ->addViolation();
                    }
                }
            }
        }
    }

    private function hasWeights(): bool
    {
        foreach ($this->questions as $question) {
            foreach ($question->answers as $answer) {
                if ([] !== $answer->weights) {
                    return true;
                }
            }
        }

        return false;
    }
}
