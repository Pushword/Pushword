<?php

namespace Pushword\Conversation\EventListener;

use Pushword\Core\Site\SiteRegistry;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The conversation form is fetched by its own HTTP request (see the `conversation()`
 * Twig function), so the locale it must render in travels as `?locale=`, not as the
 * request's own host or route. Resolving it in the controller would be too late:
 * Symfony's LocaleAwareListener has already pushed the request locale onto the
 * translator and the validator by then, and the form would answer in the kernel's
 * default locale instead of the embedding page's.
 *
 * Running between the router (32) and Symfony's LocaleListener (16) lets `_locale`
 * go through the standard pipeline, so every locale-aware service — not just the
 * translator — sees the requested locale.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 20)]
final readonly class ConversationLocaleListener
{
    public function __construct(
        private SiteRegistry $siteRegistry,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if ('pushword_conversation' !== $request->attributes->getString('_route')) {
            return;
        }

        $host = $request->query->getString('host') ?: $request->getHost();
        $locale = $request->query->getString('locale') ?: $this->siteRegistry->get($host)->locale;

        $request->attributes->set('_locale', $locale);
    }
}
