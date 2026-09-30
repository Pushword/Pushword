<?php

declare(strict_types=1);

namespace Pushword\Core\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Keeps every /admin response out of search engines — HTML, JSON, file previews
 * and the redirect to /login an anonymous crawler gets. A header rather than a
 * `<meta name="robots">`: extension screens under /admin don't all extend the
 * EasyAdmin layout, and non-HTML responses have no `<head>` to carry one.
 *
 * The path is matched on the segment, so a public page slugged `administration`
 * stays indexable.
 */
final class AdminNoindexListener
{
    #[AsEventListener(event: ResponseEvent::class)]
    public function __invoke(ResponseEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        if ('/admin' !== $path && ! str_starts_with($path, '/admin/')) {
            return;
        }

        $event->getResponse()->headers->set('X-Robots-Tag', 'noindex, nofollow');
    }
}
