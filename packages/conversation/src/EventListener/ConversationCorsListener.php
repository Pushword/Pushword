<?php

declare(strict_types=1);

namespace Pushword\Conversation\EventListener;

use Pushword\Core\Site\SiteRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The conversation form is fetched and posted by the pages embedding it, from another
 * origin when a statically generated site posts to its live host.
 *
 * A foreign origin is refused on the request, before the controller runs: its posts
 * must not spend the visitor's submission quota. An allowed origin gets the CORS
 * headers on every response of the route, errors included: without them the browser
 * hides a 404 or a 429 from the embedding page, whose fetch fails with a bare network
 * error.
 */
final readonly class ConversationCorsListener
{
    private const string ALLOWED_ORIGIN_ATTRIBUTE = '_conversation_allowed_origin';

    public function __construct(
        private SiteRegistry $siteRegistry,
        #[Autowire(param: 'kernel.environment')]
        private string $env,
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST)]
    public function checkOrigin(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $origin = $request->headers->get('origin');

        if (null === $origin || 'pushword_conversation' !== $request->attributes->getString('_route')) {
            return;
        }

        if (! \in_array($origin, $this->getAllowedOrigins($request), true)) {
            throw new AccessDeniedHttpException(\sprintf('Origin `%s` is not allowed to load conversation forms.', $origin));
        }

        $request->attributes->set(self::ALLOWED_ORIGIN_ATTRIBUTE, $origin);
    }

    #[AsEventListener(event: KernelEvents::RESPONSE)]
    public function addCorsHeaders(ResponseEvent $event): void
    {
        $origin = $event->getRequest()->attributes->get(self::ALLOWED_ORIGIN_ATTRIBUTE);

        if (! \is_string($origin)) {
            return;
        }

        $event->getResponse()->headers->add([
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Allow-Credentials' => 'true',
            // Lets the embedding page read how long a rate-limited visitor must wait.
            'Access-Control-Expose-Headers' => 'Retry-After',
        ]);
    }

    /**
     * @return string[]
     */
    private function getAllowedOrigins(Request $request): array
    {
        $site = $this->siteRegistry->get($request->query->getString('host') ?: $request->getHost());
        $configuredOrigins = $site->get('conversation_possible_origins');
        $allowedOrigins = \is_string($configuredOrigins) ? explode(' ', $configuredOrigins) : [];

        if ('dev' === $this->env) {
            // Trust the dev server's own origin whatever port it picked (the
            // hardcoded 8000-8002 miss any other one, e.g. when 8000 is taken).
            $allowedOrigins[] = $request->getSchemeAndHttpHost();
            $allowedOrigins[] = 'http://'.$request->getHost();
            $allowedOrigins[] = 'https://'.$request->getHost();
            $allowedOrigins[] = 'http://'.$request->getHost().':8000';
            $allowedOrigins[] = 'http://'.$request->getHost().':8001';
            $allowedOrigins[] = 'http://'.$request->getHost().':8002';
        }

        foreach ($site->hosts as $host) {
            $allowedOrigins[] = 'https://'.$host;
        }

        return $allowedOrigins;
    }
}
