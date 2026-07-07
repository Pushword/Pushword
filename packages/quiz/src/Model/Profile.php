<?php

namespace Pushword\Quiz\Model;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * A personality-test outcome (an "archetype"). In `mode: profile` quizzes there
 * is no correct answer: each answer carries {@see Answer::$weights} pointing at
 * one or more profile `key`s, and the profile with the highest tally wins. Its
 * `title`/`msg`/`media` make up the result card shown at the end.
 */
class Profile
{
    public function __construct(
        #[Assert\NotBlank(message: 'quiz.profile.key.empty')]
        public string $key = '',
        #[Assert\NotBlank(message: 'quiz.profile.title.empty')]
        public string $title = '',
        public ?string $msg = null,
        public ?string $media = null,
        public ?string $alt = null,
    ) {
    }
}
