<?php

namespace Pushword\Quiz\Twig;

use Pushword\Quiz\Model\Answer;
use Pushword\Quiz\Model\Profile;
use Pushword\Quiz\Model\Question;
use Pushword\Quiz\Service\QuizRenderer;
use Twig\Attribute\AsTwigFunction;

/**
 * Twig facade for the quiz block. Two ways to declare a quiz inline in a page:
 *
 *   {{ quiz('…json…') }}            — the JSON is a single-quoted Twig string;
 *                                     literal apostrophes must be escaped (\').
 *   {% quiz %}{ …json… }{% endquiz %} — the JSON is the raw tag body; apostrophes
 *                                     need no escaping (see {@see QuizTagExtension}).
 *
 * Both forms share {@see QuizRenderer}. `quiz_figure`/`quiz_answer_image` render
 * a question/answer illustration, swallowing a missing-media error so one broken
 * file never 500s the page.
 */
final readonly class QuizExtension
{
    public function __construct(
        private QuizRenderer $renderer,
    ) {
    }

    #[AsTwigFunction('quiz', isSafe: ['html'])]
    public function renderQuiz(string $json): string
    {
        return $this->renderer->render($json);
    }

    #[AsTwigFunction('quiz_figure', isSafe: ['html'])]
    public function renderFigure(Question $question): string
    {
        return $this->renderer->renderFigure($question);
    }

    #[AsTwigFunction('quiz_answer_image', isSafe: ['html'])]
    public function renderAnswerImage(Answer $answer): string
    {
        return $this->renderer->renderAnswerImage($answer);
    }

    #[AsTwigFunction('quiz_profile_image', isSafe: ['html'])]
    public function renderProfileImage(Profile $profile): string
    {
        return $this->renderer->renderProfileImage($profile);
    }
}
