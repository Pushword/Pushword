<?php

declare(strict_types=1);

namespace Pushword\Flat\Service;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Entity\User;
use Symfony\Component\HttpFoundation\Request;

/**
 * Validates API tokens for webhook lock/unlock operations.
 * Tokens are stored per-user in the User entity.
 */
final readonly class FlatApiTokenValidator
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * Validate a Bearer token and return the associated user.
     */
    public function validateToken(string $token): ?User
    {
        if ('' === $token) {
            return null;
        }

        return $this->em->getRepository(User::class)->findOneBy(['apiToken' => $token]);
    }

    /**
     * Extract Bearer token from Authorization header.
     */
    public function extractTokenFromRequest(Request $request): ?string
    {
        $authHeader = $request->headers->get('Authorization');

        if (null === $authHeader || ! str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        return substr($authHeader, 7);
    }

    /**
     * Validate request and return the associated user.
     */
    public function validateRequest(Request $request): ?User
    {
        $token = $this->extractTokenFromRequest($request);

        if (null === $token) {
            return null;
        }

        return $this->validateToken($token);
    }
}
