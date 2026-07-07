<?php

namespace Pushword\Quiz\Service;

use Pushword\Quiz\Model\Answer;
use Pushword\Quiz\Model\Profile;
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
        $mode = $this->stringOr($data['mode'] ?? null, 'quiz');
        $isProfile = 'profile' === $mode;

        // A personality test has no difficulty levels; it always owns its questions.
        $levels = $isProfile ? [] : $this->buildLevels($data['levels'] ?? null, $data);

        return new Quiz(
            title: $this->stringOrNull($data['title'] ?? null),
            // `levels` owns the questions; the root keeps only the shared metadata.
            questions: [] === $levels ? $this->buildQuestions($data['questions'] ?? null) : [],
            // No correct answer to reveal, so a personality test only ever scores at the end.
            feedback: $isProfile ? 'end' : $this->stringOr($data['feedback'] ?? null, 'immediate'),
            difficulty: $this->stringOrNull($data['difficulty'] ?? null),
            results: $this->buildResults($data['results'] ?? null),
            cta: $this->stringOrNull($data['cta'] ?? null),
            ctaTitle: $this->stringOrNull($data['ctaTitle'] ?? null),
            numbering: $this->stringOr($data['numbering'] ?? null, ''),
            labels: $this->buildLabels($data['labels'] ?? null),
            pass: $this->intOrNull($data['pass'] ?? null),
            label: $this->stringOrNull($data['label'] ?? null),
            levels: $levels,
            mode: $mode,
            profiles: $this->buildProfiles($data['profiles'] ?? null),
        );
    }

    /**
     * Build the difficulty levels, each a leaf quiz inheriting the root's shared
     * metadata when it does not override it. Recursion is bound to a single depth:
     * a level never reads its own `levels` key.
     *
     * @param array<string, mixed> $root
     *
     * @return Quiz[]
     */
    private function buildLevels(mixed $raw, array $root): array
    {
        if (! \is_array($raw) || [] === $raw) {
            return [];
        }

        $levels = [];
        foreach ($raw as $entry) {
            if (! \is_array($entry)) {
                continue;
            }

            $levels[] = $this->buildLevel($entry, $root);
        }

        return $levels;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $root
     */
    private function buildLevel(array $data, array $root): Quiz
    {
        $rootLabels = \is_array($root['labels'] ?? null) ? $root['labels'] : [];
        $levelLabels = \is_array($data['labels'] ?? null) ? $data['labels'] : [];

        return new Quiz(
            questions: $this->buildQuestions($data['questions'] ?? null),
            feedback: $this->stringOr($data['feedback'] ?? $root['feedback'] ?? null, 'immediate'),
            difficulty: $this->stringOrNull($data['difficulty'] ?? null),
            results: $this->buildResults($data['results'] ?? $root['results'] ?? null),
            cta: $this->stringOrNull($data['cta'] ?? $root['cta'] ?? null),
            ctaTitle: $this->stringOrNull($data['ctaTitle'] ?? $root['ctaTitle'] ?? null),
            numbering: $this->stringOr($data['numbering'] ?? $root['numbering'] ?? null, ''),
            labels: $this->buildLabels(array_merge($rootLabels, $levelLabels)),
            pass: $this->intOrNull($data['pass'] ?? $root['pass'] ?? null),
            label: $this->stringOrNull($data['label'] ?? null),
            levels: [],
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
                weights: $this->buildWeights($answer['weights'] ?? null, $answer['profile'] ?? null),
            );
        }

        return $answers;
    }

    /**
     * Personality-mode answer weights: a `{profileKey: points}` map, plus the
     * `profile: "key"` shorthand (== `{key: 1}`) which never overrides an explicit map entry.
     *
     * @return array<string, int>
     */
    private function buildWeights(mixed $weights, mixed $profile): array
    {
        $out = [];
        if (\is_array($weights)) {
            foreach ($weights as $key => $value) {
                if (\is_string($key) && '' !== $key && is_numeric($value)) {
                    $out[$key] = (int) $value;
                }
            }
        }

        if (\is_string($profile) && '' !== $profile && ! isset($out[$profile])) {
            $out[$profile] = 1;
        }

        return $out;
    }

    /**
     * @return Profile[]
     */
    private function buildProfiles(mixed $raw): array
    {
        if (! \is_array($raw)) {
            return [];
        }

        $profiles = [];
        foreach ($raw as $profile) {
            if (! \is_array($profile)) {
                continue;
            }

            $profiles[] = new Profile(
                key: $this->toString($profile['key'] ?? ''),
                title: $this->toString($profile['title'] ?? ''),
                msg: $this->stringOrNull($profile['msg'] ?? $profile['message'] ?? null),
                media: $this->stringOrNull($profile['media'] ?? null),
                alt: $this->stringOrNull($profile['alt'] ?? null),
            );
        }

        return $profiles;
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

    private function intOrNull(mixed $value): ?int
    {
        if (\is_int($value)) {
            return $value;
        }

        return is_numeric($value) ? (int) $value : null;
    }
}
