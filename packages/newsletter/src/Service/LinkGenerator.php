<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Service;

use Pushword\Core\Site\SiteRegistry;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Contact;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Absolute URLs for the links a newsletter carries.
 *
 * By default they use the audience host's `base_live_url`, where PHP runs on
 * static sites. An occurrence may explicitly choose a different system-link
 * base without changing that default for other newsletters.
 */
final readonly class LinkGenerator
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private SiteRegistry $siteRegistry,
    ) {
    }

    /**
     * The double opt-in link — and, asked for, the variant that also grants the
     * click-tracking consent: one click answers both when the confirmation mail
     * carries the two buttons side by side.
     */
    public function confirmUrl(Contact $contact, bool $withClickTracking = false): string
    {
        $route = $withClickTracking ? 'pushword_newsletter_confirm_tracking' : 'pushword_newsletter_confirm';

        return $this->base($contact->audience)
            .$this->urlGenerator->generate($route, ['token' => $contact->token]);
    }

    public function unsubscribeUrl(Contact $contact, ?string $mainHost = null, ?string $systemLinkBaseUrl = null): string
    {
        return $this->base($contact->audience, $mainHost, $systemLinkBaseUrl)
            .$this->urlGenerator->generate('pushword_newsletter_unsubscribe', ['token' => $contact->token], UrlGeneratorInterface::ABSOLUTE_PATH);
    }

    public function base(Audience $audience, ?string $mainHost = null, ?string $systemLinkBaseUrl = null): string
    {
        if (null !== $systemLinkBaseUrl) {
            return rtrim($systemLinkBaseUrl, '/');
        }

        $site = $this->siteRegistry->get($mainHost ?? $audience->mainHost);
        $base = $site->getStr('base_live_url');

        return rtrim('' !== $base ? $base : $site->baseUrl, '/');
    }
}
