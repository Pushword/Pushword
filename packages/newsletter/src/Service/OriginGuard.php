<?php

namespace Pushword\Newsletter\Service;

use Pushword\Core\Site\SiteRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CORS for the public subscribe endpoint.
 *
 * A statically generated site posts to the origin where PHP runs, so the browser
 * sends a cross-origin request. Allowed origins come from
 * `newsletter_possible_origins`, falling back to the conversation setting — a
 * site that already declared where its forms are posted from should not have to
 * declare it twice.
 */
final class OriginGuard
{
    /** @var string[]|null */
    private ?array $possibleOrigins = null;

    public function __construct(
        private readonly SiteRegistry $siteRegistry,
        #[Autowire(param: 'kernel.environment')]
        private readonly string $env,
    ) {
    }

    /**
     * Build the response the endpoint will use, with CORS headers when the
     * origin is known. An unknown origin gets no header, so the browser drops
     * the response — the request itself is still handled and rate-limited.
     */
    public function respond(Request $request): Response
    {
        $response = new Response();
        $origin = $request->headers->get('origin');

        if (null === $origin || ! \in_array($origin, $this->possibleOrigins($request), true)) {
            return $response;
        }

        $response->headers->set('Access-Control-Allow-Methods', 'POST, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Origin, Content-Type, Accept');
        $response->headers->set('Access-Control-Allow-Origin', $origin);

        return $response;
    }

    /**
     * Reset per-request state so a long-running worker cannot pin every later
     * request to the first one's host.
     */
    public function reset(): void
    {
        $this->possibleOrigins = null;
    }

    /** @return string[] */
    private function possibleOrigins(Request $request): array
    {
        if (null !== $this->possibleOrigins) {
            return $this->possibleOrigins;
        }

        $site = $this->siteRegistry->get();
        $configured = $site->get('newsletter_possible_origins') ?? $site->get('conversation_possible_origins');

        $origins = \is_string($configured) ? explode(' ', $configured) : [];

        foreach ($site->getHosts() as $host) {
            $origins[] = 'https://'.$host;
        }

        if ('dev' === $this->env) {
            $origins[] = $request->getSchemeAndHttpHost();
        }

        return $this->possibleOrigins = array_values(array_filter($origins, static fn (string $origin): bool => '' !== $origin));
    }
}
