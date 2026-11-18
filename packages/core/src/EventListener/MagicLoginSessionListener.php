<?php

declare(strict_types=1);

namespace Pushword\Core\EventListener;

use Pushword\Core\Entity\User;
use Pushword\Core\Repository\LoginTokenRepository;
use Pushword\Core\Security\MagicLinkAuthenticator;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final readonly class MagicLoginSessionListener
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private LoginTokenRepository $tokens,
        private UrlGeneratorInterface $urls,
    ) {
    }

    // After the firewall, before any controller can use a recovered link's old session.
    #[AsEventListener(event: RequestEvent::class, priority: 0)]
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (! $event->isMainRequest() || ! $request->hasPreviousSession() || $request->attributes->getBoolean('_stateless')) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        // Impersonation must not hide the link session that started it.
        if ($token instanceof SwitchUserToken) {
            $token = $token->getOriginalToken();
        }

        if (! $token instanceof TokenInterface || ! $token->hasAttribute(MagicLinkAuthenticator::TOKEN_ID)) {
            return;
        }

        $id = $token->getAttribute(MagicLinkAuthenticator::TOKEN_ID);
        $version = $token->getAttribute(MagicLinkAuthenticator::TOKEN_VERSION);
        $context = $token->getAttribute(MagicLinkAuthenticator::TOKEN_CONTEXT);
        $user = $token->getUser();
        if (! \is_int($id) || ! \is_int($version) || ! \is_string($context) || ! $user instanceof User) {
            return;
        }

        if ($this->tokens->isSessionCurrent($id, $version, $user)) {
            return;
        }

        $this->tokenStorage->setToken(null);
        $request->getSession()->remove('_security_'.$context);
        $request->getSession()->migrate(true);
        $response = new RedirectResponse($this->urls->generate('pushword_login'));
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->clearCookie(PwAuthCookieListener::COOKIE_NAME, '/');

        $event->setResponse($response);
    }
}
