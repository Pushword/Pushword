<?php

namespace Pushword\Api\Controller;

use Pushword\Core\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationListInterface;

abstract class AbstractApiController extends AbstractController implements ApiControllerInterface
{
    protected function getApiUser(): User
    {
        $user = $this->getUser();
        if (! $user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeJson(Request $request): array
    {
        $raw = $request->getContent();
        if ('' === $raw) {
            return [];
        }

        $decoded = json_decode($raw, true);

        return \is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed>|null          $body
     * @param array<string, string|list<string>> $headers
     */
    protected function respond(?array $body, int $status = Response::HTTP_OK, array $headers = []): JsonResponse
    {
        return new JsonResponse($body, $status, $headers);
    }

    protected function notFound(string $message = 'Not found'): JsonResponse
    {
        return $this->respond(['error' => $message], Response::HTTP_NOT_FOUND);
    }

    protected function badRequest(string $message): JsonResponse
    {
        return $this->respond(['error' => $message], Response::HTTP_BAD_REQUEST);
    }

    protected function noContent(): JsonResponse
    {
        return $this->respond(null, Response::HTTP_NO_CONTENT);
    }

    protected function validationErrors(ConstraintViolationListInterface $violations): JsonResponse
    {
        $body = [
            'error' => 'validation',
            'violations' => [],
        ];

        foreach ($violations as $violation) {
            $body['violations'][] = [
                'path' => $violation->getPropertyPath(),
                'message' => (string) $violation->getMessage(),
            ];
        }

        return $this->respond($body, Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @return array{page: int, perPage: int, offset: int}
     */
    protected function paginationParams(Request $request, int $defaultPerPage = 25, int $maxPerPage = 100): array
    {
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = $request->query->getInt('per_page', $defaultPerPage);
        $perPage = max(1, min($maxPerPage, $perPage));

        return [
            'page' => $page,
            'perPage' => $perPage,
            'offset' => ($page - 1) * $perPage,
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return array<string, mixed>
     */
    protected function paginated(array $items, int $total, int $page, int $perPage): array
    {
        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }
}
