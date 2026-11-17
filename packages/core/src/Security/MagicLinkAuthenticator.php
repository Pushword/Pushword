<?php

declare(strict_types=1);

namespace Pushword\Core\Security;

use LogicException;
use Pushword\Core\Entity\LoginToken;
use Pushword\Core\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class MagicLinkAuthenticator extends AbstractAuthenticator
{
    use TargetPathTrait;

    public const string TOKEN_ID = 'pushword_login_token_id';

    public const string TOKEN_VERSION = 'pushword_login_token_version';

    public const string TOKEN_CONTEXT = 'pushword_login_token_context';

    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public function supports(Request $request): ?bool
    {
        // This authenticator is only used programmatically via UserAuthenticatorInterface
        return false;
    }

    public function authenticate(Request $request): Passport
    {
        throw new LogicException('This authenticator must be used through UserAuthenticatorInterface::authenticateUser().');
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $loginToken = $request->attributes->get(LoginToken::class);
        if ($loginToken instanceof LoginToken) {
            $token->setAttribute(self::TOKEN_ID, $loginToken->id);
            $token->setAttribute(self::TOKEN_VERSION, $loginToken->sessionVersion);
            $token->setAttribute(self::TOKEN_CONTEXT, $firewallName);
        }

        if (($user = $token->getUser()) instanceof User && $user->requiresPasswordChange()) {
            return new RedirectResponse($this->urlGenerator->generate('pushword_login_change_password'));
        }

        if (($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) !== null) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate('pushword_admin'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new RedirectResponse($this->urlGenerator->generate('pushword_login'));
    }
}
