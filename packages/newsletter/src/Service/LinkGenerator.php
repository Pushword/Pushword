<?php

namespace Pushword\Newsletter\Service;

use Pushword\Core\Site\SiteRegistry;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Contact;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Absolute URLs for the links a newsletter carries.
 *
 * They are always built from the audience host's `base_live_url` — the origin
 * where PHP actually runs — never from the canonical base URL, so confirm and
 * unsubscribe keep working when the site itself is a static build.
 */
final readonly class LinkGenerator
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private SiteRegistry $siteRegistry,
    ) {
    }

    public function confirmUrl(Contact $contact): string
    {
        return $this->base($contact->audience)
            .$this->urlGenerator->generate('pushword_newsletter_confirm', ['token' => $contact->token]);
    }

    public function unsubscribeUrl(Contact $contact): string
    {
        return $this->base($contact->audience)
            .$this->urlGenerator->generate('pushword_newsletter_unsubscribe', ['token' => $contact->token]);
    }

    public function base(Audience $audience): string
    {
        $site = $this->siteRegistry->get($audience->mainHost);
        $base = $site->getStr('base_live_url');

        return rtrim('' !== $base ? $base : $site->baseUrl, '/');
    }
}
