<?php

declare(strict_types=1);

namespace Pushword\Flat\EventSubscriber;

use Pushword\Flat\Admin\FlatSyncNotifier;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AutoconfigureTag('kernel.event_subscriber')]
final readonly class FlatSyncNotifierSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private FlatSyncNotifier $notifier,
    ) {
    }

    /**
     * @return array<string, list<array{0: string, 1?: int}|int|string>|string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 10]],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        if (! str_starts_with($event->getRequest()->getPathInfo(), '/admin')) {
            return;
        }

        $this->notifier->notifyAll();
    }
}
