<?php

declare(strict_types=1);

namespace Pushword\Core\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;

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
    // Run after controller attributes, including IsGranted, have loaded the user.
    #[AsEventListener(event: ControllerArgumentsEvent::class, priority: -20000)]
    public function __invoke(ControllerArgumentsEvent $event): void
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
        if ($session->isStarted()) {
            // Release the file lock before expensive controller work. Later session
            // access reopens it normally, including CSRF, flashes and token storage.
            $session->save();
        }
    }
}
