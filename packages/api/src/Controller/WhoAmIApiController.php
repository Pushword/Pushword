<?php

namespace Pushword\Api\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class WhoAmIApiController extends AbstractApiController
{
    /**
     * Identify the user behind the API token used for the request.
     */
    #[Route('/api/whoami', name: 'pushword_api_whoami', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $user = $this->getApiUser();

        return $this->respond([
            'id' => $user->getId(),
            'email' => $user->email,
            'username' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function describe(): array
    {
        return [
            'paths' => [
                '/api/whoami' => [
                    'get' => [
                        'summary' => 'Identify the user owning the API token used for the request',
                        'responses' => [
                            '200' => [
                                'description' => 'Authenticated user identity',
                                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/WhoAmI']]],
                            ],
                            '401' => ['description' => 'Missing or invalid API token'],
                        ],
                    ],
                ],
            ],
            'components' => [
                'schemas' => [
                    'WhoAmI' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'email' => ['type' => 'string'],
                            'username' => ['type' => 'string'],
                            'roles' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                    ],
                ],
            ],
        ];
    }
}
