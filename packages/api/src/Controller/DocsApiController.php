<?php

namespace Pushword\Api\Controller;

use Pushword\Api\Service\OpenApiBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DocsApiController extends AbstractApiController
{
    public function __construct(
        private readonly OpenApiBuilder $builder,
    ) {
    }

    #[Route('/api/docs', name: 'pushword_api_docs', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return new JsonResponse($this->builder->build(), Response::HTTP_OK, [
            'Cache-Control' => 'public, max-age=60',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function describe(): array
    {
        return [
            'paths' => [
                '/api/docs' => [
                    'get' => [
                        'summary' => 'Self-describing OpenAPI 3.1 document',
                        'security' => [],
                        'responses' => [
                            '200' => ['description' => 'OpenAPI document', 'content' => ['application/json' => ['schema' => ['type' => 'object']]]],
                        ],
                    ],
                ],
            ],
        ];
    }
}
