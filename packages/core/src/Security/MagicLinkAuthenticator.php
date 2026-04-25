<?php

namespace Pushword\Core\Security;

use LogicException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class MagicLinkAuthenticator extends AbstractAuthenticator
{
    use TargetPathTrait;

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
        throw new LogicException('This method should not be called directly. Use createPassport() instead.');
    }

    public function createPassport(string $userIdentifier): Passport
    {
        return new SelfValidatingPassport(new UserBadge($userIdentifier));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
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
