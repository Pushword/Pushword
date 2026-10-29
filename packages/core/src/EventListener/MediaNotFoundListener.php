<?php

declare(strict_types=1);

namespace Pushword\Core\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Avoid rendering the site's error page for missing or malformed media URLs. */
#[AsEventListener(event: ExceptionEvent::class, priority: 10)]
final readonly class MediaNotFoundListener
{
    public function __construct(private string $publicMediaDir)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if (! $event->isMainRequest()
            || ! $exception instanceof HttpExceptionInterface
            || Response::HTTP_NOT_FOUND !== $exception->getStatusCode()) {
            return;
        }

        $prefix = '/'.trim($this->publicMediaDir, '/');
        $path = rawurldecode($event->getRequest()->getPathInfo());
        if ($path !== $prefix && ! str_starts_with($path, $prefix.'/')) {
            return;
        }

        $event->setResponse(new Response('', Response::HTTP_NOT_FOUND, ['Content-Type' => 'text/plain; charset=UTF-8']));
    }
}
