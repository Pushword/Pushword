<?php

namespace Pushword\Newsletter\Controller;

use Psr\Cache\CacheItemPoolInterface;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Repository\AudienceRepository;
use Pushword\Newsletter\Repository\ContactRepository;
use Pushword\Newsletter\Service\ContactManager;
use Pushword\Newsletter\Service\OriginGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The public surface: subscribe, confirm, unsubscribe.
 *
 * All three are reachable on the live host even when the site is statically
 * generated, which is why the links a mail carries are built from
 * `base_live_url` rather than from the canonical base URL.
 */
final class NewsletterController extends AbstractController
{
    /** A honeypot input real people never see, and never fill. */
    private const string HONEYPOT_FIELD = 'website';

    /** Subscriptions accepted from one IP per hour. */
    private const int RATE_LIMIT = 10;

    private const int RATE_WINDOW = 3600;

    public function __construct(
        private readonly AudienceRepository $audienceRepository,
        private readonly ContactRepository $contactRepository,
        private readonly ContactManager $contactManager,
        private readonly OriginGuard $originGuard,
        private readonly SiteRegistry $siteRegistry,
        private readonly TranslatorInterface $translator,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    #[Route(
        path: '/newsletter/subscribe',
        name: 'pushword_newsletter_subscribe',
        methods: ['POST', 'OPTIONS'],
    )]
    public function subscribe(Request $request): Response
    {
        $this->originGuard->reset();
        $response = $this->originGuard->respond($request);

        if ($request->isMethod('OPTIONS')) {
            $response->setStatusCode(Response::HTTP_NO_CONTENT);

            return $response;
        }

        // A filled honeypot gets the success page a human would get: a prober
        // must not be able to tell a rejected submission from an accepted one.
        if ('' !== trim((string) $request->request->get(self::HONEYPOT_FIELD, ''))) {
            return $this->alert($response, 'newsletter.subscribe.pending', 'success');
        }

        $audience = $this->audienceRepository->findOneBySlug((string) $request->request->get('audience', ''));
        if (! $audience instanceof Audience) {
            return $this->alert($response, 'newsletter.subscribe.unknownAudience', 'error', Response::HTTP_NOT_FOUND);
        }

        $email = trim((string) $request->request->get('email', ''));
        if (false === filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            return $this->alert($response, 'newsletter.subscribe.invalidEmail', 'error', Response::HTTP_BAD_REQUEST);
        }

        if (! $this->withinRateLimit($request)) {
            return $this->alert($response, 'newsletter.subscribe.tooMany', 'error', Response::HTTP_TOO_MANY_REQUESTS);
        }

        $contact = $this->contactManager->subscribe(
            $audience,
            $email,
            (string) $request->request->get('name', ''),
            $this->resolveLocale($request),
            $audience->filterInterests($this->submittedInterests($request)),
            $this->resolveSource($request),
            $this->resolveOptinHost($request),
            $request->getClientIp(),
        );

        return $this->alert(
            $response,
            $contact->isPending() ? 'newsletter.subscribe.pending' : 'newsletter.subscribe.done',
            'success',
        );
    }

    #[Route(
        path: '/newsletter/confirm/{token}',
        name: 'pushword_newsletter_confirm',
        requirements: ['token' => '[a-f0-9]{64}'],
        methods: ['GET'],
    )]
    public function confirm(string $token): Response
    {
        $contact = $this->contactRepository->findOneByToken($token);

        if (! $contact instanceof Contact) {
            return $this->page('unknown.html.twig', null, Response::HTTP_NOT_FOUND);
        }

        $this->contactManager->confirm($contact);

        return $this->page('confirmed.html.twig', $contact);
    }

    /**
     * GET only shows the page: a mail scanner following the link must not
     * unsubscribe anyone. The actual opt-out is the POST, which is also what
     * RFC 8058 one-click sends.
     */
    #[Route(
        path: '/newsletter/unsubscribe/{token}',
        name: 'pushword_newsletter_unsubscribe',
        requirements: ['token' => '[a-f0-9]{64}'],
        methods: ['GET', 'POST'],
    )]
    public function unsubscribe(string $token, Request $request): Response
    {
        $contact = $this->contactRepository->findOneByToken($token);

        if (! $contact instanceof Contact) {
            return $this->page('unknown.html.twig', null, Response::HTTP_NOT_FOUND);
        }

        if ($request->isMethod('GET') && null === $contact->getUnsubscribedAt()) {
            return $this->page('unsubscribe.html.twig', $contact);
        }

        $this->contactManager->unsubscribe($contact);

        return $this->page('unsubscribed.html.twig', $contact);
    }

    private function page(string $template, ?Contact $contact, int $status = Response::HTTP_OK): Response
    {
        $audience = $contact?->getAudience();
        $view = $this->siteRegistry->get($audience?->getMainHost())
            ->getView('/newsletter/'.$template, '@PushwordNewsletter');

        return $this->render($view, [
            'contact' => $contact,
            'audience' => $audience,
        ], new Response(status: $status));
    }

    private function alert(Response $response, string $message, string $context, int $status = Response::HTTP_OK): Response
    {
        $view = $this->siteRegistry->get()->getView('/newsletter/alert.html.twig', '@PushwordNewsletter');

        $response->setStatusCode($status);
        $response->setContent($this->renderView($view, [
            'message' => $this->translator->trans($message),
            'context' => $context,
        ]));

        return $response;
    }

    /**
     * The endpoint is public, cross-origin and sends a mail on success: without
     * a ceiling it is a way to deliver confirmation mails to arbitrary addresses.
     */
    private function withinRateLimit(Request $request): bool
    {
        $item = $this->cache->getItem('pushword_newsletter_subscribe_'.md5((string) $request->getClientIp()));
        $count = $item->isHit() && \is_int($item->get()) ? $item->get() : 0;

        if ($count >= self::RATE_LIMIT) {
            return false;
        }

        $item->set($count + 1);
        $item->expiresAfter(self::RATE_WINDOW);

        $this->cache->save($item);

        return true;
    }

    /** @return string[] */
    private function submittedInterests(Request $request): array
    {
        $submitted = $request->request->all()['interests'] ?? [];

        if (\is_string($submitted)) {
            $submitted = explode(',', $submitted);
        }

        if (! \is_array($submitted)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $i): string => \is_scalar($i) ? trim((string) $i) : '', $submitted),
            static fn (string $i): bool => '' !== $i,
        ));
    }

    private function resolveLocale(Request $request): string
    {
        $locale = trim((string) $request->request->get('locale', ''));

        return '' !== $locale ? $locale : $this->siteRegistry->get()->getLocale();
    }

    private function resolveSource(Request $request): ?string
    {
        $source = trim((string) $request->request->get('source', ''));

        if ('' !== $source) {
            return $source;
        }

        $referer = $request->headers->get('referer');

        return null !== $referer ? (parse_url($referer, \PHP_URL_PATH) ?: null) : null;
    }

    /** Where the form was served from. Provenance only: consent is scoped to the audience. */
    private function resolveOptinHost(Request $request): string
    {
        $origin = $request->headers->get('origin');

        if (null !== $origin) {
            $host = parse_url($origin, \PHP_URL_HOST);
            if (\is_string($host) && '' !== $host) {
                return $host;
            }
        }

        return $request->getHost();
    }
}
