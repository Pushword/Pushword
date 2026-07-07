<?php

namespace Pushword\Quiz\Service;

use Pushword\Conversation\Twig\AppExtension;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Twig\MediaExtension;
use Pushword\Core\Twig\VideoExtension;
use Pushword\Quiz\Model\Answer;
use Pushword\Quiz\Model\Profile;
use Pushword\Quiz\Model\Question;

use function Safe\json_decode;

use Symfony\Bundle\SecurityBundle\Security;
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
 * Tolerant by design on two axes: a malformed payload degrades gracefully
 * (admins see a detailed error panel, visitors see nothing) instead of 500-ing
 * the page, and a missing/broken illustration is skipped rather than fataling
 * the whole render.
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
        private readonly Security $security,
        private readonly MediaExtension $mediaExtension,
        private readonly VideoExtension $videoExtension,
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
            ]);
        } catch (Throwable $throwable) {
            // Last-resort guard: a broken template must never 500 the page.
            // Per-illustration failures are already swallowed in renderFigure().
            return $this->renderError(['Rendering failed: '.$throwable->getMessage()]);
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
        } catch (Throwable $throwable) {
            return $this->mediaWarning($throwable);
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
        } catch (Throwable $throwable) {
            return $this->mediaWarning($throwable);
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
        } catch (Throwable $throwable) {
            return $this->mediaWarning($throwable);
        }
    }

    private function mediaWarning(Throwable $throwable): string
    {
        if (! $this->isAdmin()) {
            return '';
        }

        return '<span class="pw-quiz-media-error" role="alert" style="display:block;color:#9f1239;font-size:.85rem">⚠ '
            .htmlspecialchars($throwable->getMessage()).'</span>';
    }

    /**
     * @param string[] $messages
     */
    private function renderError(array $messages): string
    {
        if (! $this->isAdmin()) {
            return '';
        }

        $items = implode('', array_map(
            static fn (string $message): string => '<li>'.htmlspecialchars($message).'</li>',
            $messages,
        ));

        return '<div class="pw-quiz-error" role="alert" style="border:2px solid #e11d48;background:#fff1f2;'
            .'color:#9f1239;padding:1rem;border-radius:.5rem;margin:1rem 0">'
            .'<strong>⚠ Invalid quiz</strong>'
            .'<ul style="margin:.5rem 0 0;padding-left:1.25rem">'.$items.'</ul></div>';
    }

    private function isAdmin(): bool
    {
        try {
            return $this->security->isGranted('ROLE_ADMIN');
        } catch (Throwable) {
            return false;
        }
    }
}
