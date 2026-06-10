<?php

namespace Pushword\Quiz\Service;

use Pushword\Quiz\Model\Answer;
use Pushword\Quiz\Model\Question;
use Pushword\Quiz\Model\Quiz;
use Pushword\Quiz\Model\ResultBand;

/**
 * Hydrates a decoded-JSON array into a {@see Quiz} object graph. Tolerant by
 * design: missing/typed-wrong values fall back to defaults, letting the
 * validator report precise, human-readable errors instead of fatals.
 */
final class QuizFactory
{
    /**
     * @param array<string, mixed> $data
     */
    public function fromArray(array $data): Quiz
    {
        return new Quiz(
            title: $this->stringOrNull($data['title'] ?? null),
            questions: $this->buildQuestions($data['questions'] ?? null),
            feedback: $this->stringOr($data['feedback'] ?? null, 'immediate'),
            difficulty: $this->stringOrNull($data['difficulty'] ?? null),
            results: $this->buildResults($data['results'] ?? null),
            cta: $this->stringOrNull($data['cta'] ?? null),
            ctaTitle: $this->stringOrNull($data['ctaTitle'] ?? null),
            numbering: $this->stringOr($data['numbering'] ?? null, ''),
            labels: $this->buildLabels($data['labels'] ?? null),
        );
    }

    /**
     * @return array<string, string>
     */
    private function buildLabels(mixed $raw): array
    {
        if (! \is_array($raw)) {
            return [];
        }

        $labels = [];
        foreach ($raw as $key => $value) {
            if (\is_string($key) && \is_string($value) && '' !== $value) {
                $labels[$key] = $value;
            }
        }

        return $labels;
    }

    /**
     * @return Question[]
     */
    private function buildQuestions(mixed $raw): array
    {
        if (! \is_array($raw)) {
            return [];
        }

        $questions = [];
        foreach ($raw as $question) {
            if (! \is_array($question)) {
                continue;
            }

            $questions[] = new Question(
                q: $this->toString($question['q'] ?? $question['text'] ?? ''),
                answers: $this->buildAnswers($question['answers'] ?? null),
                media: $this->stringOrNull($question['media'] ?? null),
                video: $this->stringOrNull($question['video'] ?? null),
                alt: $this->stringOrNull($question['alt'] ?? null),
                explanation: $this->stringOrNull($question['explanation'] ?? null),
            );
        }

        return $questions;
    }

    /**
     * @return Answer[]
     */
    private function buildAnswers(mixed $raw): array
    {
        if (! \is_array($raw)) {
            return [];
        }

        $answers = [];
        foreach ($raw as $answer) {
            if (! \is_array($answer)) {
                continue;
            }

            $answers[] = new Answer(
                text: $this->toString($answer['a'] ?? $answer['text'] ?? ''),
                correct: (bool) ($answer['correct'] ?? false),
                media: $this->stringOrNull($answer['media'] ?? null),
                alt: $this->stringOrNull($answer['alt'] ?? null),
            );
        }

        return $answers;
    }

    /**
     * @return ResultBand[]
     */
    private function buildResults(mixed $raw): array
    {
        if (! \is_array($raw)) {
            return [];
        }

        $results = [];
        foreach ($raw as $band) {
            if (! \is_array($band)) {
                continue;
            }

            $results[] = new ResultBand(
                min: $this->toInt($band['min'] ?? 0),
                msg: $this->toString($band['msg'] ?? $band['message'] ?? ''),
            );
        }

        return $results;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return \is_string($value) && '' !== $value ? $value : null;
    }

    private function stringOr(mixed $value, string $default): string
    {
        return \is_string($value) ? $value : $default;
    }

    private function toString(mixed $value): string
    {
        if (\is_string($value)) {
            return $value;
        }

        return \is_scalar($value) ? (string) $value : '';
    }

    private function toInt(mixed $value): int
    {
        if (\is_int($value)) {
            return $value;
        }

        return is_numeric($value) ? (int) $value : 0;
    }
}
