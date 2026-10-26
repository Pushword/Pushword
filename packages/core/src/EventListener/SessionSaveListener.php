<?php

declare(strict_types=1);

namespace Pushword\Core\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Saves an admin request's started session before its controller runs, so a slow
 * render or upload does not hold PHP's session file lock against the editor's other
 * requests.
 *
 * Admin only: Symfony silently refuses to migrate or invalidate a session closed this
 * way, so login, logout and site controllers keep theirs open.
 */
final readonly class SessionSaveListener
{
    private const string RELEASED_SIGNED_IN = '_pw_session_released_signed_in';

    public function __construct(
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    // Run after controller attributes, including IsGranted, have loaded the user.
    #[AsEventListener(event: ControllerArgumentsEvent::class, priority: -20000)]
    public function release(ControllerArgumentsEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();
        if (! $event->isMainRequest()
            || ('/admin' !== $path && ! str_starts_with($path, '/admin/'))
            || $request->attributes->getBoolean('_stateless')
            || ! $request->hasSession(true)) {
            return;
        }

        $session = $request->getSession();
        if (! $session->isStarted()) {
            return;
        }

        $request->attributes->set(self::RELEASED_SIGNED_IN, $this->holdsSignIn($request));
        // Release the file lock before expensive controller work. Later session
        // access reopens it normally, including CSRF, flashes and token storage.
        $session->save();
    }

    /**
     * A sign-out in another tab while this request ran has emptied the session. The
     * firewall would still write this request's token back into it at the end and
     * sign the editor in again, so the token is dropped instead.
     *
     * Priority 1 runs before the firewall's ContextListener (priority 0) writes the token back.
     */
    #[AsEventListener(event: ResponseEvent::class, priority: 1)]
    public function keepSignOut(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (! $event->isMainRequest()
            || true !== $request->attributes->get(self::RELEASED_SIGNED_IN)
            || $this->holdsSignIn($request)) {
            return;
        }

        $this->tokenStorage->setToken(null);
    }

    /** The firewall stores the token under `_security_<context>`; reading reopens the session. */
    private function holdsSignIn(Request $request): bool
    {
        return array_any(
            array_keys($request->getSession()->all()),
            static fn (string $key): bool => str_starts_with($key, '_security_'),
        );
    }
}
