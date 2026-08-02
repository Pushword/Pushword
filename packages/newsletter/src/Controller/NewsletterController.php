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
use Pushword\Newsletter\Service\SubscribeForm;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Translation\LocaleSwitcher;
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
        private readonly SubscribeForm $subscribeForm,
        private readonly SiteRegistry $siteRegistry,
        private readonly TranslatorInterface $translator,
        private readonly LocaleSwitcher $localeSwitcher,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * The form itself, fetched by the placeholder `newsletter_form()` leaves in
     * the page. Everything it takes is already what the subscribe endpoint
     * accepts from anyone, so serving it opens nothing the POST did not.
     */
    #[Route(
        path: '/newsletter/form',
        name: 'pushword_newsletter_form',
        methods: ['GET', 'POST', 'OPTIONS'],
    )]
    public function form(Request $request): Response
    {
        $this->originGuard->reset();
        $response = $this->originGuard->respond($request);

        if ($request->isMethod('OPTIONS')) {
            $response->setStatusCode(Response::HTTP_NO_CONTENT);

            return $response;
        }

        $audiences = $this->subscribeForm->audiences($this->submittedList($request->query->all(), 'audiences'));

        if ([] === $audiences) {
            $response->setStatusCode(Response::HTTP_NOT_FOUND);

            return $response;
        }

        $response->setContent($this->subscribeForm->render(
            $audiences,
            $this->subscribeForm->declaredInterests($audiences, $this->submittedList($request->query->all(), 'interests')),
            $this->resolveLocale($request->query->all()),
            $request->query->getString('source') ?: null,
        ));

        return $response;
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

        // The request reaches the live host, whose own locale says nothing about
        // the reader; the form sends the locale of the page it sat in.
        $locale = $this->resolveLocale($request->request->all());
        $this->localeSwitcher->setLocale($locale);

        // A filled honeypot gets the success page a human would get: a prober
        // must not be able to tell a rejected submission from an accepted one.
        if ('' !== trim((string) $request->request->get(self::HONEYPOT_FIELD, ''))) {
            return $this->alert($response, 'newsletter.subscribe.pending', 'success');
        }

        // Only checked where the setting turned it on, and then it is the form
        // endpoint that issued the token — a hand-rolled post has to fetch one.
        if (! $this->subscribeForm->isTokenValid($request->request->getString('_token'))) {
            return $this->alert($response, 'newsletter.subscribe.invalidToken', 'error', Response::HTTP_FORBIDDEN);
        }

        $audiences = $this->submittedAudiences($request);
        if (null === $audiences) {
            return $this->alert($response, 'newsletter.subscribe.unknownAudience', 'error', Response::HTTP_NOT_FOUND);
        }

        if ([] === $audiences) {
            return $this->alert($response, 'newsletter.subscribe.noAudience', 'error', Response::HTTP_BAD_REQUEST);
        }

        $email = trim((string) $request->request->get('email', ''));
        if (false === filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            return $this->alert($response, 'newsletter.subscribe.invalidEmail', 'error', Response::HTTP_BAD_REQUEST);
        }

        if (! $this->withinRateLimit($request)) {
            return $this->alert($response, 'newsletter.subscribe.tooMany', 'error', Response::HTTP_TOO_MANY_REQUESTS);
        }

        $interests = $this->submittedList($request->request->all(), 'interests');
        $pending = false;

        try {
            foreach ($audiences as $audience) {
                $contact = $this->contactManager->subscribe(
                    $audience,
                    $email,
                    (string) $request->request->get('name', ''),
                    $locale,
                    $audience->filterInterests($interests),
                    $this->resolveSource($request),
                    $this->resolveOptinHost($request),
                    $request->getClientIp(),
                );

                $pending = $pending || $contact->isPending();
            }
        } catch (TransportExceptionInterface) {
            // Most often the server refusing the mailbox — a typo in the address.
            // The person can fix it and resubmit; nothing pending was stored.
            return $this->alert($response, 'newsletter.subscribe.sendFailed', 'error', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // One confirmation to click is the whole story to tell: it is what stands
        // between the person and their first mail, whatever the other lists did.
        return $this->alert(
            $response,
            $pending ? 'newsletter.subscribe.pending' : 'newsletter.subscribe.done',
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
     * One click for a person, a page to confirm for everything else.
     *
     * Somebody who clicked "unsubscribe" in a mail has already said what they
     * want, and making them say it twice is what turns an opt-out into a spam
     * report. A mail scanner following the same link clicked nothing, so it gets
     * the page instead, which acts on POST.
     *
     * The RFC 8058 one-click POST lands here too, for the same effect.
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

        // Once they are gone there is nothing left to confirm, so a second visit
        // goes through to the page keeping the other lists within reach.
        if ($request->isMethod('GET') && ! $this->clicked($request) && null === $contact->getUnsubscribedAt()) {
            return $this->page('unsubscribe.html.twig', $contact);
        }

        $this->contactManager->unsubscribe($contact);

        return $this->unsubscribed($contact);
    }

    /**
     * Did a person click, or did something fetch the link?
     *
     * `Sec-Fetch-User` is sent as `?1` only for a navigation the browser
     * attributes to a real gesture. A scanner following the link has none to
     * report, whatever user agent it claims — which is why this recognises
     * clicks rather than trying to recognise scanners. A browser too old to send
     * it (Safari before 16.4) reads as a fetch too: one more click for its
     * reader, never the wrong outcome.
     */
    private function clicked(Request $request): bool
    {
        return '?1' === $request->headers->get('Sec-Fetch-User');
    }

    /**
     * Leave the other lists of the same host too.
     *
     * They are offered as a choice, never acted on with the first opt-out:
     * consent is scoped to one audience, and so is leaving. Only someone
     * opening the link themselves ever gets here — the RFC 8058 one-click POST
     * is sent by the mailbox provider, which shows the response to nobody.
     */
    #[Route(
        path: '/newsletter/unsubscribe/{token}/others',
        name: 'pushword_newsletter_unsubscribe_others',
        requirements: ['token' => '[a-f0-9]{64}'],
        methods: ['POST'],
    )]
    public function unsubscribeOthers(string $token, Request $request): Response
    {
        $contact = $this->contactRepository->findOneByToken($token);

        if (! $contact instanceof Contact) {
            return $this->page('unknown.html.twig', null, Response::HTTP_NOT_FOUND);
        }

        $all = $request->request->has('all');
        $submitted = $this->submittedList($request->request->all(), 'audiences');

        // The siblings are re-read here rather than trusted from the form: the
        // slugs decide nothing, they only pick from what the token may touch.
        foreach ($this->contactRepository->findSubscribedSiblings($contact) as $sibling) {
            if ($all || \in_array($sibling->getAudience()->getSlug(), $submitted, true)) {
                $this->contactManager->unsubscribe($sibling);
            }
        }

        return $this->unsubscribed($contact);
    }

    private function unsubscribed(Contact $contact): Response
    {
        return $this->page(
            'unsubscribed.html.twig',
            $contact,
            siblings: $this->contactRepository->findSubscribedSiblings($contact),
        );
    }

    /**
     * An audience covers several language hosts by design, but the links it sends
     * are all built from one host's `base_live_url` — so the request locale here
     * is that host's, not the reader's. The contact carries their own: translate
     * in it, as the mail that brought them the link already does.
     *
     * @param list<Contact> $siblings
     */
    private function page(string $template, ?Contact $contact, int $status = Response::HTTP_OK, array $siblings = []): Response
    {
        $audience = $contact?->getAudience();
        $view = $this->siteRegistry->get($audience?->getMainHost())
            ->getView('/newsletter/'.$template, '@PushwordNewsletter');

        if (null !== $contact) {
            $this->localeSwitcher->setLocale($contact->getLocale());
        }

        return $this->render($view, [
            'contact' => $contact,
            'audience' => $audience,
            'siblings' => $siblings,
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

    /**
     * The lists a submission is for: `audiences[]` when the form offers several,
     * `audience` when it offers one.
     *
     * Each is a consent of its own, so an unknown slug fails the whole
     * submission — half a subscription is not what the person ticked.
     *
     * @return list<Audience>|null null when a submitted slug matches nothing
     */
    private function submittedAudiences(Request $request): ?array
    {
        $slugs = $this->submittedList($request->request->all(), 'audiences');

        if ([] === $slugs) {
            $single = trim((string) $request->request->get('audience', ''));
            $slugs = '' !== $single ? [$single] : [];
        }

        $audiences = [];

        foreach (array_unique($slugs) as $slug) {
            $audience = $this->audienceRepository->findOneBySlug($slug);

            if (! $audience instanceof Audience) {
                return null;
            }

            $audiences[] = $audience;
        }

        return $audiences;
    }

    /**
     * @param array<string, mixed> $submitted the query or the body, as sent
     *
     * @return string[]
     */
    private function submittedList(array $submitted, string $field): array
    {
        $submitted = $submitted[$field] ?? [];

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

    /** @param array<string, mixed> $submitted the query or the body, as sent */
    private function resolveLocale(array $submitted): string
    {
        $sent = $submitted['locale'] ?? null;
        $locale = \is_scalar($sent) ? trim((string) $sent) : '';

        return '' !== $locale ? $locale : $this->siteRegistry->get()->locale;
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
