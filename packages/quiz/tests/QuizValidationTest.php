<?php

namespace Pushword\Quiz\Tests;

use DateTime;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Component\EntityFilter\ManagerPool;
use Pushword\Core\Entity\Page;
use Pushword\Core\Site\RequestContext;
use Pushword\Quiz\Editor\QuizEditorToolProvider;
use Pushword\Quiz\Service\QuizFactory;
use Pushword\Quiz\Twig\QuizExtension;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

#[Group('integration')]
final class QuizValidationTest extends KernelTestCase
{
    /**
     * @return array<string, mixed>
     */
    private function validQuiz(): array
    {
        return [
            'title' => 'Capitals',
            'questions' => [
                [
                    'q' => 'Capital of France?',
                    'answers' => [
                        ['a' => 'Paris', 'correct' => true],
                        ['a' => 'Lyon'],
                    ],
                ],
            ],
        ];
    }

    public function testValidQuizPasses(): void
    {
        self::bootKernel();
        $quiz = self::getContainer()->get(QuizFactory::class)->fromArray($this->validQuiz());
        $violations = self::getContainer()->get(ValidatorInterface::class)->validate($quiz);

        self::assertCount(0, $violations);
    }

    public function testInvalidQuizReportsPreciseViolations(): void
    {
        self::bootKernel();
        $quiz = self::getContainer()->get(QuizFactory::class)->fromArray([
            'questions' => [
                ['q' => '', 'answers' => [['a' => 'only']]],
            ],
        ]);
        $violations = self::getContainer()->get(ValidatorInterface::class)->validate($quiz);

        $paths = [];
        foreach ($violations as $violation) {
            $paths[] = $violation->getPropertyPath();
        }

        self::assertContains('questions[0].q', $paths); // empty question
        self::assertContains('questions[0].answers', $paths); // < 2 answers + no correct
    }

    public function testMalformedJsonDoesNotThrowAndRendersNothingForVisitors(): void
    {
        self::bootKernel();
        // Graceful degradation: a broken payload must never bubble up (no 500).
        $output = self::getContainer()->get(QuizExtension::class)->renderQuiz('this is not json');

        self::assertSame('', $output);
    }

    public function testRenderLocalizesLabelDefaults(): void
    {
        self::bootKernel();
        self::getContainer()->get(RequestContext::class)->setRequestContext('localhost.dev');
        $extension = self::getContainer()->get(QuizExtension::class);
        $translator = self::getContainer()->get(TranslatorInterface::class);

        $json = '{"questions":[{"q":"Capital of France?","answers":'
            .'[{"a":"Paris","correct":true},{"a":"Lyon"}],"explanation":"Paris is the capital."}]}';

        $translator->setLocale('en');
        $en = $extension->renderQuiz($json);
        self::assertStringContainsString('>Explanation<', $en);
        // `score`/`better` feed only the JS config (no server-rendered HTML).
        self::assertStringContainsString('Your score:', $en);
        self::assertStringContainsString('Better than {p}% of participants', $en);

        $translator->setLocale('fr');
        self::assertStringContainsString('>Explication<', $extension->renderQuiz($json));
    }

    public function testRenderLabelsHonorJsonOverride(): void
    {
        self::bootKernel();
        self::getContainer()->get(RequestContext::class)->setRequestContext('localhost.dev');
        $extension = self::getContainer()->get(QuizExtension::class);
        self::getContainer()->get(TranslatorInterface::class)->setLocale('fr');

        $json = '{"labels":{"explanation":"Note"},"questions":[{"q":"Capital of France?",'
            .'"answers":[{"a":"Paris","correct":true},{"a":"Lyon"}],"explanation":"Paris is the capital."}]}';
        $output = $extension->renderQuiz($json);

        self::assertStringContainsString('>Note<', $output);
        self::assertStringNotContainsString('>Explication<', $output);
    }

    public function testProfileMessageRendersMarkdown(): void
    {
        self::bootKernel();
        self::getContainer()->get(RequestContext::class)->setRequestContext('localhost.dev');
        $extension = self::getContainer()->get(QuizExtension::class);

        $json = '{"mode":"profile","profiles":[{"key":"a","title":"A",'
            .'"msg":"Read the [guide](https://example.com)."}],'
            .'"questions":[{"q":"Q?","answers":[{"a":"Yes","profile":"a"},{"a":"No"}]}]}';
        $output = $extension->renderQuiz($json);

        self::assertStringContainsString('<div class="pw-quiz-profile-msg">', $output);
        self::assertStringContainsString('href="https://example.com"', $output);
        self::assertStringNotContainsString('[guide]', $output);
    }

    public function testResultBandMessageRendersMarkdown(): void
    {
        self::bootKernel();
        self::getContainer()->get(RequestContext::class)->setRequestContext('localhost.dev');
        $extension = self::getContainer()->get(QuizExtension::class);

        // The band message is delivered to the JS runtime as pre-rendered HTML
        // inside the config <script>; Markdown must be resolved server-side.
        $json = '{"questions":[{"q":"Q?","answers":[{"a":"Yes","correct":true},{"a":"No"}]}],'
            .'"results":[{"min":0,"msg":"See the [guide](https://example.com)."}]}';
        $output = $extension->renderQuiz($json);

        // Hardening: angle brackets are hex-escaped inside the JSON <script>, so
        // the embedded HTML can never prematurely close it (no </script> break-out).
        self::assertStringContainsString('\\u003Ca href=', $output);
        self::assertStringNotContainsString('<a href=', $output);

        // …yet it decodes back to real, Markdown-rendered HTML for the runtime.
        $bandMsg = $this->firstBandMessage($output);
        self::assertStringContainsString('href="https://example.com"', $bandMsg);
        self::assertStringNotContainsString('[guide]', $bandMsg);
    }

    /**
     * Pull the first score band's message out of the JS runtime config embedded
     * in a rendered quiz.
     */
    private function firstBandMessage(string $html): string
    {
        self::assertSame(1, preg_match('#class="pw-quiz-config">(.*?)</script>#s', $html, $matches));

        $config = json_decode($matches[1], true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($config);
        self::assertArrayHasKey('results', $config);
        self::assertIsArray($config['results']);
        self::assertArrayHasKey(0, $config['results']);
        self::assertIsArray($config['results'][0]);
        self::assertArrayHasKey('msg', $config['results'][0]);
        self::assertIsString($config['results'][0]['msg']);

        return $config['results'][0]['msg'];
    }

    public function testEditorProviderExposesQuizTool(): void
    {
        self::bootKernel();
        $tools = self::getContainer()->get(QuizEditorToolProvider::class)->getToolsConfig('localhost.dev');

        self::assertArrayHasKey('quiz', $tools);
        self::assertSame('Quiz', $tools['quiz']['className']);
    }

    public function testFactoryAcceptsKeyAliases(): void
    {
        self::bootKernel();
        // `text`/`a` for answers, `text`/`q` for questions, `message`/`msg` for bands.
        $quiz = self::getContainer()->get(QuizFactory::class)->fromArray([
            'questions' => [
                [
                    'text' => 'Aliased question',
                    'answers' => [['text' => 'Yes', 'correct' => true], ['text' => 'No']],
                ],
            ],
            'results' => [['min' => 50, 'message' => 'Half']],
        ]);

        self::assertSame('Aliased question', $quiz->questions[0]->q);
        self::assertSame('Yes', $quiz->questions[0]->answers[0]->text);
        self::assertSame('Half', $quiz->results[0]->msg);
        self::assertCount(0, self::getContainer()->get(ValidatorInterface::class)->validate($quiz));
    }

    public function testVideoQuestionRequiresAlt(): void
    {
        self::bootKernel();
        $quiz = self::getContainer()->get(QuizFactory::class)->fromArray([
            'questions' => [
                [
                    'q' => 'Watch then answer',
                    'video' => 'https://youtube.com/watch?v=x',
                    'media' => 'poster.jpg', // poster present, so only the alt is missing
                    'answers' => [['a' => 'A', 'correct' => true], ['a' => 'B']],
                ],
            ],
        ]);

        $paths = [];
        foreach (self::getContainer()->get(ValidatorInterface::class)->validate($quiz) as $violation) {
            $paths[] = $violation->getPropertyPath();
        }

        self::assertContains('questions[0].alt', $paths);
    }

    public function testVideoQuestionRequiresPoster(): void
    {
        self::bootKernel();
        // A video uses the `media` image as its poster, so one is mandatory.
        $quiz = self::getContainer()->get(QuizFactory::class)->fromArray([
            'questions' => [
                [
                    'q' => 'Watch then answer',
                    'video' => 'https://youtube.com/watch?v=x',
                    'alt' => 'A short clip', // alt present, so only the poster is missing
                    'answers' => [['a' => 'A', 'correct' => true], ['a' => 'B']],
                ],
            ],
        ]);

        $paths = [];
        foreach (self::getContainer()->get(ValidatorInterface::class)->validate($quiz) as $violation) {
            $paths[] = $violation->getPropertyPath();
        }

        self::assertContains('questions[0].media', $paths);
    }

    public function testFactoryParsesNumberingAndLabels(): void
    {
        self::bootKernel();
        $quiz = self::getContainer()->get(QuizFactory::class)->fromArray([
            'numbering' => 'A',
            'cta' => 'newsletter',
            'ctaTitle' => 'Receive the next quizzes in your mailbox',
            'labels' => ['explanation' => 'Explication', 'score' => 'Votre score :'],
            'questions' => [['q' => 'Q', 'answers' => [['a' => 'A', 'correct' => true], ['a' => 'B']]]],
        ]);

        self::assertSame('A', $quiz->numbering);
        self::assertSame('newsletter', $quiz->cta);
        self::assertSame('Receive the next quizzes in your mailbox', $quiz->ctaTitle);
        self::assertSame('Explication', $quiz->labels['explanation']);
        self::assertCount(0, self::getContainer()->get(ValidatorInterface::class)->validate($quiz));
    }

    public function testInvalidNumberingIsRejected(): void
    {
        self::bootKernel();
        $quiz = self::getContainer()->get(QuizFactory::class)->fromArray([
            'numbering' => 'Z',
            'questions' => [['q' => 'Q', 'answers' => [['a' => 'A', 'correct' => true], ['a' => 'B']]]],
        ]);

        $paths = [];
        foreach (self::getContainer()->get(ValidatorInterface::class)->validate($quiz) as $violation) {
            $paths[] = $violation->getPropertyPath();
        }

        self::assertContains('numbering', $paths);
    }

    public function testEmptyQuizWithoutQuestionsOrLevelsIsRejected(): void
    {
        self::bootKernel();
        // No questions and no levels: the structure callback must complain.
        $quiz = self::getContainer()->get(QuizFactory::class)->fromArray([]);

        $paths = [];
        foreach (self::getContainer()->get(ValidatorInterface::class)->validate($quiz) as $violation) {
            $paths[] = $violation->getPropertyPath();
        }

        self::assertContains('questions', $paths);
    }

    /**
     * @return array<string, mixed>
     */
    private function leveledQuiz(): array
    {
        $level = static fn (string $difficulty): array => [
            'difficulty' => $difficulty,
            'questions' => [
                ['q' => 'Q in '.$difficulty, 'answers' => [['a' => 'A', 'correct' => true], ['a' => 'B']]],
            ],
        ];

        return [
            'title' => 'Mountains',
            'levels' => [$level('Easy'), $level('Hard')],
        ];
    }

    public function testLevelsQuizValidates(): void
    {
        self::bootKernel();
        $quiz = self::getContainer()->get(QuizFactory::class)->fromArray($this->leveledQuiz());

        // The root carries no questions of its own once it delegates to levels.
        self::assertCount(0, $quiz->questions);
        self::assertCount(2, $quiz->levels);
        self::assertCount(0, self::getContainer()->get(ValidatorInterface::class)->validate($quiz));
    }

    public function testLevelWithoutQuestionsIsRejected(): void
    {
        self::bootKernel();
        $quiz = self::getContainer()->get(QuizFactory::class)->fromArray([
            'title' => 'Mountains',
            'levels' => [
                ['difficulty' => 'Easy', 'questions' => [['q' => 'Q', 'answers' => [['a' => 'A', 'correct' => true], ['a' => 'B']]]]],
                ['difficulty' => 'Empty', 'questions' => []],
            ],
        ]);

        $paths = [];
        foreach (self::getContainer()->get(ValidatorInterface::class)->validate($quiz) as $violation) {
            $paths[] = $violation->getPropertyPath();
        }

        self::assertContains('levels[1].questions', $paths);
    }

    public function testLevelInheritsRootMetadata(): void
    {
        self::bootKernel();
        $quiz = self::getContainer()->get(QuizFactory::class)->fromArray([
            'cta' => 'newsletter',
            'pass' => 60,
            'labels' => ['explanation' => 'Exp'],
            'levels' => [
                [
                    'difficulty' => 'Easy',
                    'labels' => ['score' => 'Sc'],
                    'questions' => [['q' => 'Q', 'answers' => [['a' => 'A', 'correct' => true], ['a' => 'B']]]],
                ],
            ],
        ]);

        $level = $quiz->levels[0];
        self::assertSame('Easy', $level->difficulty);
        self::assertSame('newsletter', $level->cta, 'cta inherited from the root');
        self::assertSame(60, $level->pass, 'pass inherited from the root');
        self::assertSame('Exp', $level->labels['explanation'], 'root labels merged in');
        self::assertSame('Sc', $level->labels['score'], 'level labels override/extend the root');
    }

    public function testPassThresholdOutOfRangeIsRejected(): void
    {
        self::bootKernel();
        $quiz = self::getContainer()->get(QuizFactory::class)->fromArray([
            'pass' => 150,
            'questions' => [['q' => 'Q', 'answers' => [['a' => 'A', 'correct' => true], ['a' => 'B']]]],
        ]);

        $paths = [];
        foreach (self::getContainer()->get(ValidatorInterface::class)->validate($quiz) as $violation) {
            $paths[] = $violation->getPropertyPath();
        }

        self::assertContains('pass', $paths);
    }

    public function testNestedLevelsAreIgnored(): void
    {
        self::bootKernel();
        // Recursion is bound to a single depth: a level never carries its own levels.
        $quiz = self::getContainer()->get(QuizFactory::class)->fromArray([
            'levels' => [
                [
                    'difficulty' => 'Easy',
                    'questions' => [['q' => 'Q', 'answers' => [['a' => 'A', 'correct' => true], ['a' => 'B']]]],
                    'levels' => [['difficulty' => 'Nested', 'questions' => []]],
                ],
            ],
        ]);

        self::assertCount(0, $quiz->levels[0]->levels);
        self::assertCount(0, self::getContainer()->get(ValidatorInterface::class)->validate($quiz));
    }

    /**
     * @return array<string, mixed>
     */
    private function profileQuiz(): array
    {
        return [
            'mode' => 'profile',
            'title' => 'Which explorer are you?',
            'profiles' => [
                ['key' => 'sommet', 'title' => 'The Summiteer', 'msg' => 'Higher, always.'],
                ['key' => 'calm', 'title' => 'The Contemplative'],
            ],
            'questions' => [
                [
                    'q' => 'A free weekend, you…',
                    'answers' => [
                        ['a' => 'climb a peak', 'weights' => ['sommet' => 2]],
                        ['a' => 'walk by a lake', 'profile' => 'calm'],
                    ],
                ],
            ],
        ];
    }

    public function testValidProfileQuizPasses(): void
    {
        self::bootKernel();
        $quiz = self::getContainer()->get(QuizFactory::class)->fromArray($this->profileQuiz());

        self::assertSame('profile', $quiz->mode);
        // Personality mode always scores at the end (no correct answer to reveal).
        self::assertSame('end', $quiz->feedback);
        // An explicit weights map is parsed as-is; the `profile` shorthand becomes {key: 1}.
        self::assertSame(['sommet' => 2], $quiz->questions[0]->answers[0]->weights);
        self::assertSame(['calm' => 1], $quiz->questions[0]->answers[1]->weights);
        self::assertCount(0, self::getContainer()->get(ValidatorInterface::class)->validate($quiz));
    }

    public function testProfileWithEmptyKeyOrTitleIsRejected(): void
    {
        self::bootKernel();
        $quiz = self::getContainer()->get(QuizFactory::class)->fromArray([
            'mode' => 'profile',
            'profiles' => [
                ['key' => '', 'title' => 'No key'],
                ['key' => 'nomsg', 'title' => ''],
            ],
            'questions' => [['q' => 'Q', 'answers' => [['a' => 'A', 'profile' => 'nomsg'], ['a' => 'B']]]],
        ]);

        $paths = [];
        foreach (self::getContainer()->get(ValidatorInterface::class)->validate($quiz) as $violation) {
            $paths[] = $violation->getPropertyPath();
        }

        self::assertContains('profiles[0].key', $paths);
        self::assertContains('profiles[1].title', $paths);
    }

    public function testProfileQuizWithoutQuestionsIsRejected(): void
    {
        self::bootKernel();
        $quiz = self::getContainer()->get(QuizFactory::class)->fromArray([
            'mode' => 'profile',
            'profiles' => [['key' => 'a', 'title' => 'A']],
            'questions' => [],
        ]);

        $paths = [];
        foreach (self::getContainer()->get(ValidatorInterface::class)->validate($quiz) as $violation) {
            $paths[] = $violation->getPropertyPath();
        }

        self::assertContains('questions', $paths);
    }

    public function testQuizModeQuestionWithoutCorrectAnswerIsRejected(): void
    {
        self::bootKernel();
        // Regression: the "at least one correct answer" rule moved from Question to
        // Quiz (so it can be skipped in profile mode). With two answers (so the
        // min-2 rule is satisfied) and none correct, it must still fire on the
        // question's answers in a knowledge quiz.
        $quiz = self::getContainer()->get(QuizFactory::class)->fromArray([
            'questions' => [['q' => 'No right answer', 'answers' => [['a' => 'A'], ['a' => 'B']]]],
        ]);

        $paths = [];
        foreach (self::getContainer()->get(ValidatorInterface::class)->validate($quiz) as $violation) {
            $paths[] = $violation->getPropertyPath();
        }

        // The only rule that can flag this (2 answers, non-empty text) is the correct-answer one.
        self::assertContains('questions[0].answers', $paths);
    }

    public function testProfileQuizWithoutProfilesIsRejected(): void
    {
        self::bootKernel();
        $data = $this->profileQuiz();
        $data['profiles'] = [];
        $quiz = self::getContainer()->get(QuizFactory::class)->fromArray($data);

        $paths = [];
        foreach (self::getContainer()->get(ValidatorInterface::class)->validate($quiz) as $violation) {
            $paths[] = $violation->getPropertyPath();
        }

        self::assertContains('profiles', $paths);
    }

    public function testProfileWeightReferencingUnknownProfileIsRejected(): void
    {
        self::bootKernel();
        $quiz = self::getContainer()->get(QuizFactory::class)->fromArray([
            'mode' => 'profile',
            'profiles' => [['key' => 'sommet', 'title' => 'The Summiteer']],
            'questions' => [['q' => 'Q', 'answers' => [
                ['a' => 'A', 'weights' => ['typo' => 2]], // no such profile
                ['a' => 'B'],
            ]]],
        ]);

        $paths = [];
        foreach (self::getContainer()->get(ValidatorInterface::class)->validate($quiz) as $violation) {
            $paths[] = $violation->getPropertyPath();
        }

        self::assertContains('questions[0].answers[0].weights', $paths);
    }

    public function testProfileQuizDoesNotRequireCorrectAnswers(): void
    {
        self::bootKernel();
        // None of the answers are `correct`; that rule must not apply in profile mode.
        $quiz = self::getContainer()->get(QuizFactory::class)->fromArray($this->profileQuiz());

        $messages = [];
        foreach (self::getContainer()->get(ValidatorInterface::class)->validate($quiz) as $violation) {
            $messages[] = (string) $violation->getMessage();
        }

        self::assertNotContains('quiz.question.correct.min', $messages);
    }

    public function testProfilesInQuizModeHintAtTheMissingMode(): void
    {
        self::bootKernel();
        // A knowledge quiz that carries profiles/weights but forgot `mode: profile`.
        $quiz = self::getContainer()->get(QuizFactory::class)->fromArray([
            'profiles' => [['key' => 'a', 'title' => 'A']],
            'questions' => [['q' => 'Q', 'answers' => [['a' => 'yes', 'profile' => 'a'], ['a' => 'no']]]],
        ]);

        $paths = [];
        foreach (self::getContainer()->get(ValidatorInterface::class)->validate($quiz) as $violation) {
            $paths[] = $violation->getPropertyPath();
        }

        self::assertContains('mode', $paths);
    }

    public function testInvalidModeIsRejected(): void
    {
        self::bootKernel();
        $quiz = self::getContainer()->get(QuizFactory::class)->fromArray([
            'mode' => 'bogus',
            'questions' => [['q' => 'Q', 'answers' => [['a' => 'A', 'correct' => true], ['a' => 'B']]]],
        ]);

        $paths = [];
        foreach (self::getContainer()->get(ValidatorInterface::class)->validate($quiz) as $violation) {
            $paths[] = $violation->getPropertyPath();
        }

        self::assertContains('mode', $paths);
    }

    public function testRenderProfileModeEmitsCardsAndSkipsQuizSchema(): void
    {
        self::bootKernel();
        self::getContainer()->get(RequestContext::class)->setRequestContext('localhost.dev');
        $extension = self::getContainer()->get(QuizExtension::class);

        $output = $extension->renderQuiz((string) json_encode($this->profileQuiz()));

        self::assertStringContainsString('pw-quiz--profile', $output);
        // Every outcome is server-rendered (SEO/no-JS), and answers carry weights.
        self::assertStringContainsString('data-profile-key="sommet"', $output);
        self::assertStringContainsString('The Summiteer', $output);
        self::assertStringContainsString('data-weights=', $output);
        // A personality test has no accepted answer → no schema.org/Quiz markup.
        self::assertStringNotContainsString('"@type":"Quiz"', $output);
        self::assertStringNotContainsString('acceptedAnswer', $output);
    }

    public function testQuizTagSurvivesTheMainContentMarkdownPipeline(): void
    {
        self::bootKernel();
        self::getContainer()->get(RequestContext::class)->setRequestContext('localhost.dev');

        $page = new Page();
        $page->setH1('Quiz pipeline page');
        $page->setSlug('quiz-pipeline');
        $page->locale = 'en';
        $page->createdAt = new DateTime('1 day ago');
        $page->updatedAt = new DateTime('1 day ago');
        $page->setMainContent(
            'Intro paragraph.'."\n\n"
            .'{% quiz %}{"questions":[{"q":"L\'eau bout à ?",'
            .'"answers":[{"a":"100°C","correct":true},{"a":"0°C"}]}]}{% endquiz %}'."\n\n"
            .'Outro paragraph.'
        );

        $manager = self::getContainer()->get(ManagerPool::class)->getManager($page);
        $html = $manager->mainContent(); // @phpstan-ignore-line (magic __call applies the main_content chain)

        self::assertIsString($html);
        // The quiz block rendered to its component markup, not a literal code block.
        self::assertStringContainsString('pw-quiz', $html);
        self::assertStringContainsString('data-correct', $html);
        self::assertStringContainsString('100°C', $html);
        // Surrounding paragraphs still went through Markdown.
        self::assertStringContainsString('<p>Intro paragraph.</p>', $html);
        self::assertStringContainsString('<p>Outro paragraph.</p>', $html);
    }

    public function testMissingQuestionMediaDoesNotFatalTheRender(): void
    {
        self::bootKernel();
        self::getContainer()->get(RequestContext::class)->setRequestContext('localhost.dev');
        $extension = self::getContainer()->get(QuizExtension::class);

        // `image()` throws on an unknown internal media; the guard must swallow it —
        // for both the question figure and an answer thumbnail — so the question
        // (and the rest of the page) still renders instead of 500-ing.
        $json = '{"questions":[{"q":"Has a broken image","media":"this-media-does-not-exist-xyz.jpg",'
            .'"answers":[{"a":"A","correct":true,"media":"missing-answer-img.jpg"},{"a":"B"}]}]}';
        $output = $extension->renderQuiz($json);

        self::assertStringContainsString('Has a broken image', $output);
        self::assertStringContainsString('pw-quiz', $output);
        // Both the question figure and the answer thumbnail were skipped, not rendered.
        self::assertStringNotContainsString('pw-quiz-media-img', $output);
        self::assertStringNotContainsString('pw-quiz-a-img', $output);
    }

    public function testQuizTagInterpolatesQuoteFreeTwig(): void
    {
        self::bootKernel();
        self::getContainer()->get(RequestContext::class)->setRequestContext('localhost.dev');
        $twig = self::getContainer()->get(Environment::class);

        // The tag body is sub-parsed as Twig: a quote-free {{ … }} is interpolated
        // before the JSON is decoded (HTML-emitting helpers would break the JSON).
        $template = '{% quiz %}{"questions":[{"q":"1 + 1 = {{ 1 + 1 }}?",'
            .'"answers":[{"a":"Two","correct":true},{"a":"Three"}]}]}{% endquiz %}';
        $output = $twig->createTemplate($template)->render();

        self::assertStringContainsString('1 + 1 = 2?', $output);
    }

    public function testQuizTagRendersUnescapedJsonBody(): void
    {
        self::bootKernel();
        self::getContainer()->get(RequestContext::class)->setRequestContext('localhost.dev');
        $twig = self::getContainer()->get(Environment::class);

        // The raw `{% quiz %}` body carries a literal apostrophe — no `\'` escaping,
        // unlike the single-quoted Twig string the `{{ quiz('…') }}` form requires.
        $template = '{% quiz %}{"questions":[{"q":"L\'eau bout à ?",'
            .'"answers":[{"a":"100°C","correct":true},{"a":"0°C"}]}]}{% endquiz %}';
        $output = $twig->createTemplate($template)->render();

        self::assertStringContainsString('pw-quiz', $output);
        self::assertStringContainsString('100°C', $output);
        // The apostrophe survived the round-trip (Twig autoescapes it in the markup).
        self::assertStringContainsString('eau bout', $output);
        self::assertStringContainsString('data-correct', $output);
    }

    public function testQuizTagAndFunctionProduceEquivalentMarkup(): void
    {
        self::bootKernel();
        self::getContainer()->get(RequestContext::class)->setRequestContext('localhost.dev');
        $twig = self::getContainer()->get(Environment::class);
        $json = '{"questions":[{"q":"Capital of France?",'
            .'"answers":[{"a":"Paris","correct":true},{"a":"Lyon"}]}]}';

        $fromTag = $twig->createTemplate('{% quiz %}'.$json.'{% endquiz %}')->render();
        $fromFunction = self::getContainer()->get(QuizExtension::class)->renderQuiz($json);

        // Same payload, same markup — only the authoring syntax differs. The
        // instance counter makes the `id`/`pw-quiz-N` differ, so compare structure.
        self::assertStringContainsString('Capital of France?', $fromTag);
        self::assertStringContainsString('Capital of France?', $fromFunction);
        self::assertSame(
            substr_count($fromFunction, 'pw-quiz-a'),
            substr_count($fromTag, 'pw-quiz-a'),
        );
    }

    public function testRenderLevelsProducesAccessibleTabs(): void
    {
        self::bootKernel();
        self::getContainer()->get(RequestContext::class)->setRequestContext('localhost.dev');
        $extension = self::getContainer()->get(QuizExtension::class);

        $output = $extension->renderQuiz((string) json_encode($this->leveledQuiz()));

        self::assertStringContainsString('pw-quiz--levels', $output);
        self::assertStringContainsString('role="tablist"', $output);
        self::assertStringContainsString('role="tab"', $output);
        self::assertStringContainsString('role="tabpanel"', $output);
        // Each level submits its percentile under a discriminated slug (no collision).
        self::assertStringContainsString('.0"', $output);
        self::assertStringContainsString('.1"', $output);
        // Tab labels fall back to the level difficulty.
        self::assertStringContainsString('>Easy</button>', $output);
    }
}
