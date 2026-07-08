<?php

namespace Pushword\Quiz\Service;

use Psr\Log\LoggerInterface;
use Pushword\Conversation\Twig\AppExtension;
use Pushword\Core\Service\EditorNotice\TwigErrorMarker;
use Pushword\Core\Service\Markdown\BrokenImageComment;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Twig\MediaExtension;
use Pushword\Core\Twig\VideoExtension;
use Pushword\Quiz\Model\Answer;
use Pushword\Quiz\Model\Profile;
use Pushword\Quiz\Model\Question;

use function Safe\json_decode;

use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;
use Twig\Environment as Twig;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * Renders a quiz from its JSON payload. Shared by the `{{ quiz('…') }}` Twig
 * function and the `{% quiz %}…{% endquiz %}` tag — registered as a Twig runtime
 * so the tag's compiled node can call it back.
 *
 * Tolerant by design on two axes, both via the shared editor-notice markers: a
 * malformed payload degrades to an invisible TwigErrorMarker (never a 500), and a
 * missing/broken illustration to a BrokenImageComment. Visitors see nothing;
 * EditorNoticeListener turns both into visible badges for ROLE_EDITOR and the page
 * scanner reports them — so the render output stays role-independent (cacheable).
 */
final class QuizRenderer implements RuntimeExtensionInterface
{
    private int $instances = 0;

    public function __construct(
        private readonly SiteRegistry $apps,
        private readonly Twig $twig,
        private readonly QuizFactory $factory,
        private readonly ValidatorInterface $validator,
        private readonly TranslatorInterface $translator,
        private readonly MediaExtension $mediaExtension,
        private readonly VideoExtension $videoExtension,
        private readonly RouterInterface $router,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function render(string $json): string
    {
        try {
            $data = json_decode($json, true);
        } catch (Throwable $throwable) {
            return $this->renderError(['Malformed JSON: '.$throwable->getMessage()]);
        }

        if (! \is_array($data)) {
            return $this->renderError(['The quiz payload must be a JSON object.']);
        }

        /** @var array<string, mixed> $data */
        $quiz = $this->factory->fromArray($data);
        $violations = $this->validator->validate($quiz);

        if (\count($violations) > 0) {
            $messages = [];
            foreach ($violations as $violation) {
                $messages[] = $violation->getPropertyPath().' — '
                    .$this->translator->trans((string) $violation->getMessage(), [], 'validators');
            }

            return $this->renderError($messages);
        }

        $template = $this->apps->get()->getView('/component/quiz.html.twig', '@PushwordQuiz');

        try {
            return $this->twig->render($template, [
                'quiz' => $quiz,
                'page' => $this->apps->getCurrentPage(),
                'id' => 'pw-quiz-'.(++$this->instances),
                'conversationAvailable' => class_exists(AppExtension::class),
                'resultEndpoint' => $this->resolveResultEndpoint(),
            ]);
        } catch (Throwable $throwable) {
            // Last-resort guard: a broken template must never 500 the page.
            // Per-illustration failures are already swallowed in renderFigure().
            return $this->renderError(['Rendering failed: '.$throwable->getMessage()]);
        }
    }

    /**
     * Absolute (live-host) endpoint the runtime posts attempts to, generated here
     * rather than in the template so a failure can be contained: recording results
     * is an optional enhancement, and an unregistered `pushword_quiz_result` route
     * must not blank out the whole quiz (SEO/no-JS Q&A included). On failure it
     * logs a clear warning and returns '' — the runtime then falls back to the
     * relative path, and percentile/share simply stay off.
     */
    private function resolveResultEndpoint(): string
    {
        try {
            return $this->apps->get()->getStr('base_live_url')
                .$this->router->generate('pushword_quiz_result');
        } catch (Throwable $throwable) {
            $this->logger->warning('Quiz result endpoint unavailable, percentile/share disabled: '.$throwable->getMessage());

            return '';
        }
    }

    /**
     * Question illustration (image, or video using the image as its poster),
     * guarded so a missing/broken media file skips the figure instead of
     * fataling the page an editor is iterating on.
     */
    public function renderFigure(Question $question): string
    {
        try {
            if (null !== $question->video) {
                $media = $this->videoExtension->renderVideo($question->video, $question->media ?? '', $question->alt ?? '');
            } elseif (null !== $question->media) {
                $media = $this->mediaExtension->renderImage(
                    $question->media,
                    alt: $question->alt ?? '',
                    class: 'pw-quiz-media-img',
                    mode: 'responsive',
                    lazy: true,
                );
            } else {
                return '';
            }
        } catch (Throwable) {
            return BrokenImageComment::for((string) ($question->media ?? $question->video));
        }

        return '<div class="pw-quiz-media">'.$media.'</div>';
    }

    /**
     * Answer thumbnail, guarded like {@see renderFigure()}.
     */
    public function renderAnswerImage(Answer $answer): string
    {
        if (null === $answer->media) {
            return '';
        }

        try {
            return $this->mediaExtension->renderImage($answer->media, alt: $answer->alt ?? '', class: 'pw-quiz-a-img', lazy: true);
        } catch (Throwable) {
            return BrokenImageComment::for($answer->media);
        }
    }

    /**
     * Personality-result card illustration, guarded like {@see renderFigure()}.
     */
    public function renderProfileImage(Profile $profile): string
    {
        if (null === $profile->media) {
            return '';
        }

        try {
            return $this->mediaExtension->renderImage(
                $profile->media,
                alt: $profile->alt ?? $profile->title,
                class: 'pw-quiz-profile-img',
                mode: 'responsive',
                lazy: true,
            );
        } catch (Throwable) {
            return BrokenImageComment::for($profile->media);
        }
    }

    /**
     * An invalid payload degrades to an invisible marker (never a 500, never
     * visible content): EditorNoticeListener turns it into a badge for ROLE_EDITOR
     * and the page scanner reports it.
     *
     * @param string[] $messages
     */
    private function renderError(array $messages): string
    {
        return TwigErrorMarker::for('Quiz: '.implode(' · ', $messages));
    }
}
