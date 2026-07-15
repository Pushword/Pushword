<?php

namespace Pushword\Quiz\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched right after a quiz attempt is recorded (POST /quiz/result) — for
 * both the on-page runtime and any other client that posts a result (e.g. an
 * in-chat quiz player).
 *
 * The event carries the whole attempt so a listener can enrich a *known*
 * visitor (attach the profile to their account, skip re-offering a done quiz…).
 * It stays PII-free at the source: the event is data-only and the persisted
 * {@see \Pushword\Quiz\Entity\QuizResult} deliberately stores none of it —
 * resolving *who* finished, and whether to keep the answers, is the listener's
 * job, in the host application, under its own consent rules.
 *
 * @phpstan-type QuizAnswer array{q: string, a: string}
 */
final class QuizCompletedEvent extends Event
{
    /**
     * @param 'profile'|'quiz' $mode    personality test vs knowledge quiz
     * @param ?string          $result  the chosen profile key (profile mode), else null
     * @param ?int             $score   the percentage score (quiz mode), else null
     * @param list<QuizAnswer> $answers the visitor's chosen answers, in order (may be empty)
     */
    public function __construct(
        private readonly string $host,
        private readonly string $quiz,
        private readonly string $mode,
        private readonly ?string $result,
        private readonly ?int $score,
        private readonly array $answers,
    ) {
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getQuiz(): string
    {
        return $this->quiz;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function getResult(): ?string
    {
        return $this->result;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    /**
     * @return list<QuizAnswer>
     */
    public function getAnswers(): array
    {
        return $this->answers;
    }
}
