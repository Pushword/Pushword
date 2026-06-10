<?php

namespace Pushword\Quiz\Twig;

use Pushword\Conversation\Twig\AppExtension;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Quiz\Model\ResultBand;
use Pushword\Quiz\Service\QuizFactory;

use function Safe\json_decode;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment as Twig;

/**
 * Renders `{{ quiz('…json…') }}`. The payload is a JSON *string* (not a Twig
 * hash) on purpose: Twig can never choke on its structure, so a malformed quiz
 * degrades gracefully (admins see a detailed error panel, visitors see nothing)
 * instead of 500-ing the whole page.
 */
final class QuizExtension
{
    /** English fallbacks for the author-defined UI words (see Quiz::$labels). */
    private const array DEFAULT_LABELS = [
        'question' => 'Question',
        'questions' => 'questions',
        'explanation' => 'Explanation',
        'score' => 'Your score:',
        'better' => 'Better than {p}% of participants',
    ];

    private int $instances = 0;

    public function __construct(
        private readonly SiteRegistry $apps,
        private readonly Twig $twig,
        private readonly QuizFactory $factory,
        private readonly ValidatorInterface $validator,
        private readonly TranslatorInterface $translator,
        private readonly Security $security,
    ) {
    }

    #[AsTwigFunction('quiz', needsEnvironment: false, isSafe: ['html'])]
    public function renderQuiz(string $json): string
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

        $results = array_map(
            static fn (ResultBand $band): array => ['min' => $band->min, 'msg' => $band->msg],
            $quiz->results,
        );

        // UI words live in the quiz JSON (author-defined, no i18n); fall back to
        // English defaults so a quiz that sets none still reads correctly.
        $labels = [...self::DEFAULT_LABELS, ...$quiz->labels];

        $template = $this->apps->get()->getView('/component/quiz.html.twig', '@PushwordQuiz');

        return $this->twig->render($template, [
            'quiz' => $quiz,
            'page' => $this->apps->getCurrentPage(),
            'id' => 'pw-quiz-'.(++$this->instances),
            'labels' => $labels,
            'config' => [
                'feedback' => $quiz->feedback,
                'results' => $results,
                'labels' => ['score' => $labels['score'], 'better' => $labels['better']],
            ],
            'conversationAvailable' => class_exists(AppExtension::class),
        ]);
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
