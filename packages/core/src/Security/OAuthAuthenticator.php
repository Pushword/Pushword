<?php

declare(strict_types=1);

namespace Pushword\Core\Security;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
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

/**
 * Generic OAuth authenticator that works with any provider configured in knpu_oauth2_client.
 */
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
        return 'pushword_oauth_check' === $request->attributes->get('_route');
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        /** @var string $provider */
        $provider = $request->attributes->get('provider');

        $client = $this->clientRegistry->getClient($provider);
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client): object {
                $oauthUser = $client->fetchUserFromToken($accessToken);

                $email = $this->extractEmail($oauthUser);

                if (null === $email || '' === $email) {
                    throw new CustomUserMessageAuthenticationException('oauthNoEmail');
                }

                $user = $this->userRepository->findOneBy(['email' => $email]);

                if (null === $user) {
                    throw new CustomUserMessageAuthenticationException('oauthUserNotFound');
                }

                return $user;
            }),
            [new RememberMeBadge()],
        );
    }

    private function extractEmail(ResourceOwnerInterface $oauthUser): ?string
    {
        // Try getEmail() method (Google, GitHub, Facebook, etc.)
        if (method_exists($oauthUser, 'getEmail')) {
            $email = $oauthUser->getEmail();

            return \is_string($email) ? $email : null;
        }

        // Try claim() method (Azure/Microsoft)
        if (method_exists($oauthUser, 'claim')) {
            $email = $oauthUser->claim('email') ?? $oauthUser->claim('preferred_username');

            return \is_string($email) ? $email : null;
        }

        // Fallback: try to get from array representation
        $data = $oauthUser->toArray();
        $email = $data['email'] ?? $data['mail'] ?? null;

        return \is_string($email) ? $email : null;
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
