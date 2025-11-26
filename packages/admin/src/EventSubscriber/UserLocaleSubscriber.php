<?php

namespace Pushword\Admin\EventSubscriber;

use Pushword\Core\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sets the Symfony locale based on the authenticated user's locale preference.
 *
 * This subscriber only applies to admin routes (/admin/*) to ensure that:
 * - Admin interface uses the user's preferred locale
 * - Public page rendering always uses page.locale (handled by PageController)
 * - No collision between user.locale and page.locale
 */
#[AutoconfigureTag('kernel.event_subscriber')]
final readonly class UserLocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Security $security,
    ) {
    }

    /**
     * @return array<string, list<array{0: string, 1?: int}|int|string>|string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Only apply to admin routes - public pages use page.locale (see PageController::show())
        // This ensures page.locale always has priority over user.locale for page rendering
        if (
            ! str_starts_with($request->getPathInfo(), '/admin')
            && ! str_starts_with($request->getPathInfo(), '/login')) {
            return;
        }

        $user = $this->security->getUser();

        if (! $user instanceof User) {
            return;
        }

        $locale = $user->getLocale();

        if ('' === $locale) {
            return;
        }

        $request->setLocale($locale);
    }
}
