<?php

declare(strict_types=1);

namespace Pushword\Core\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;

final readonly class SessionSaveListener
{
    // Run after controller attributes, including IsGranted, have loaded the user.
    #[AsEventListener(event: ControllerArgumentsEvent::class, priority: -20000)]
    public function __invoke(ControllerArgumentsEvent $event): void
    {
        $request = $event->getRequest();
        if (! $event->isMainRequest() || $request->attributes->getBoolean('_stateless') || ! $request->hasSession(true)) {
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
