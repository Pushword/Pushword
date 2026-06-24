<?php

namespace Pushword\Quiz\Controller\Api;

use Pushword\Api\Controller\AbstractApiController;
use Pushword\Quiz\Service\QuizFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Token-authenticated endpoint that validates a quiz JSON payload against the
 * same rules as the renderer and the editor lint. Built for AI agents editing
 * quizzes through the API: returns precise `{path, message}` violations (422)
 * instead of a generic error.
 */
#[IsGranted('ROLE_EDITOR')]
final class QuizApiController extends AbstractApiController
{
    public function __construct(
        private readonly QuizFactory $factory,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route(path: '/api/quiz/validate', name: 'pushword_api_quiz_validate', methods: ['POST'])]
    public function validate(Request $request): JsonResponse
    {
        $quiz = $this->factory->fromArray($this->decodeJson($request));

        $violations = $this->validator->validate($quiz);
        if (\count($violations) > 0) {
            return $this->validationErrors($violations);
        }

        $questions = \count($quiz->questions);
        foreach ($quiz->levels as $level) {
            $questions += \count($level->questions);
        }

        return $this->respond([
            'valid' => true,
            'questions' => $questions,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function describe(): array
    {
        return [
            'paths' => [
                '/api/quiz/validate' => [
                    'post' => [
                        'summary' => 'Validate a quiz JSON payload',
                        'description' => 'Returns {valid:true} on success, or 422 with {violations:[{path,message}]}.',
                        'tags' => ['Quiz'],
                        'requestBody' => [
                            'required' => true,
                            'content' => ['application/json' => ['schema' => ['type' => 'object']]],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Valid quiz'],
                            '422' => ['description' => 'Validation errors'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
