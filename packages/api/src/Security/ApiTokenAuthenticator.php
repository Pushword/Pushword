<?php

namespace Pushword\Api\Security;

use Pushword\Core\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class ApiTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $request->headers->has('Authorization')
            && str_starts_with((string) $request->headers->get('Authorization'), 'Bearer ');
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $header = (string) $request->headers->get('Authorization');
        $token = substr($header, 7);

        if ('' === $token) {
            throw new CustomUserMessageAuthenticationException('Missing API token');
        }

        return new SelfValidatingPassport(
            new UserBadge($token, function (string $userToken): object {
                $user = $this->userRepository->findOneBy(['apiToken' => $userToken]);
                if (null === $user) {
                    throw new CustomUserMessageAuthenticationException('Invalid API token');
                }

                return $user;
            }),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        return new JsonResponse(
            ['error' => 'unauthenticated', 'message' => $exception->getMessageKey()],
            Response::HTTP_UNAUTHORIZED,
        );
    }
}
