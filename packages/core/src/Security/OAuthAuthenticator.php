<?php

declare(strict_types=1);

namespace Pushword\Core\Security;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\GoogleUser;
use Pushword\Core\Repository\UserRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use TheNetworg\OAuth2\Client\Provider\AzureResourceOwner;

final class OAuthAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly RouterInterface $router,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function supports(Request $request): bool
    {
        return \in_array($request->attributes->get('_route'), [
            'pushword_oauth_google_check',
            'pushword_oauth_microsoft_check',
        ], true);
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $route = $request->attributes->get('_route');
        $clientName = 'pushword_oauth_google_check' === $route ? 'google' : 'microsoft';

        $client = $this->clientRegistry->getClient($clientName);
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client): object {
                $oauthUser = $client->fetchUserFromToken($accessToken);

                $email = match (true) {
                    $oauthUser instanceof GoogleUser => $oauthUser->getEmail(),
                    $oauthUser instanceof AzureResourceOwner => $oauthUser->claim('email') ?? $oauthUser->claim('preferred_username'),
                    default => throw new CustomUserMessageAuthenticationException('oauthUnsupportedProvider'),
                };

                if (null === $email || '' === $email) {
                    throw new CustomUserMessageAuthenticationException('oauthNoEmail');
                }

                $user = $this->userRepository->findOneBy(['email' => $email]);

                if (null === $user) {
                    throw new CustomUserMessageAuthenticationException('oauthUserNotFound');
                }

                return $user;
            }),
            [new RememberMeBadge()]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): RedirectResponse
    {
        /** @var string $targetUrl */
        $targetUrl = $request->getSession()->get('_security.main.target_path')
            ?? $this->router->generate('pushword_admin');

        return new RedirectResponse($targetUrl);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): RedirectResponse
    {
        $request->getSession()->set('oauth_error', $exception->getMessageKey());

        return new RedirectResponse($this->router->generate('pushword_login'));
    }
}
