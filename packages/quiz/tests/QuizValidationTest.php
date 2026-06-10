<?php

namespace Pushword\Quiz\Tests;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Quiz\Editor\QuizEditorToolProvider;
use Pushword\Quiz\Service\QuizFactory;
use Pushword\Quiz\Twig\QuizExtension;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

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
}
