<?php

namespace Pushword\Newsletter\Click;

use Pushword\Core\Component\EntityFilter\Filter\HtmlUnpublishedLink;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\AutomationStep;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Service\LinkGenerator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Rewrites the links of a mail body through `/newsletter/c/{payload}`, so a
 * click can be recorded before the reader is sent on — and only behind two
 * consents: the audience's `clickTracking` switch and the contact's own dated
 * `clickTrackingConsentAt`. Either one missing leaves the body's links exactly
 * as the UTM pass left them.
 *
 * The payload names the contact, the mail (a campaign, or an automation step)
 * and the destination, and is signed with an HMAC on the kernel secret: the
 * endpoint redirects wherever the payload says, so an unsigned payload would be
 * an open redirect wearing our domain. A signature that does not recompute is a
 * 404, never a redirect.
 *
 * Only the body passes through here, after {@see \Pushword\Newsletter\Utm\UtmDecorator}
 * and before the template wraps it — so the destination stays `utm_*`-tagged,
 * and the template's own links, the unsubscribe link first among them, are
 * never rewritten: leaving must not be a thing this install records.
 */
final readonly class ClickTracker
{
    private const int SIGNATURE_LENGTH = 32;

    public function __construct(
        private LinkGenerator $linkGenerator,
        private UrlGeneratorInterface $urlGenerator,
        #[Autowire(param: 'kernel.secret')]
        private string $secret,
    ) {
    }

    public function rewrite(string $html, Audience $audience, Contact $contact, Campaign|AutomationStep $mail): string
    {
        if (! $audience->clickTracking || ! $contact->hasClickTrackingConsent() || ! str_contains($html, '<a ')) {
            return $html;
        }

        return preg_replace_callback(
            HtmlUnpublishedLink::HTML_REGEX,
            fn (array $match): string => $this->trackLink($match, $audience, $contact, $mail),
            $html
        ) ?? $html;
    }

    /** @param array<int|string, string> $match */
    private function trackLink(array $match, Audience $audience, Contact $contact, Campaign|AutomationStep $mail): string
    {
        $url = html_entity_decode($match['href'], \ENT_QUOTES | \ENT_HTML5);
        $parts = parse_url($url);

        // A mailto:, a tel:, an anchor: no page visit to record behind them.
        if (false === $parts
            || ! isset($parts['scheme'], $parts['host'])
            || ! \in_array($parts['scheme'], ['http', 'https'], true)) {
            return $match[0];
        }

        // Built from base_live_url like every link the bundle serves itself, so
        // the endpoint answers even when the site is a static build.
        $tracked = $this->linkGenerator->base($audience)
            .$this->urlGenerator->generate('pushword_newsletter_click', ['payload' => $this->payload($contact, $mail, $url)]);

        $quote = $match['quote'];

        return '<a'.$match['before'].'href='.$quote.htmlspecialchars($tracked, \ENT_QUOTES | \ENT_HTML5).$quote
            .$match['after'].'>'.$match['content'].'</a>';
    }

    /** @return ClickPayload|null null when the signature does not recompute, or nothing legible sits under it */
    public function decode(string $payload): ?ClickPayload
    {
        $parts = explode('.', $payload, 2);

        if (2 !== \count($parts) || ! hash_equals($this->sign($parts[0]), $parts[1])) {
            return null;
        }

        $json = base64_decode(strtr($parts[0], '-_', '+/'), true);
        $data = false !== $json ? json_decode($json, true) : null;

        if (! \is_array($data) || ! \is_int($data['c'] ?? null) || ! \is_string($data['u'] ?? null)) {
            return null;
        }

        return new ClickPayload(
            contactId: $data['c'],
            url: $data['u'],
            campaignId: \is_int($data['k'] ?? null) ? $data['k'] : null,
            automationId: \is_int($data['a'] ?? null) ? $data['a'] : null,
            position: \is_int($data['p'] ?? null) ? $data['p'] : null,
        );
    }

    private function payload(Contact $contact, Campaign|AutomationStep $mail, string $url): string
    {
        $data = ['c' => $contact->id, 'u' => $url];

        if ($mail instanceof Campaign) {
            $data['k'] = $mail->id;
        } else {
            $data['a'] = $mail->automation?->id;
            $data['p'] = $mail->position;
        }

        $encoded = rtrim(strtr(base64_encode(json_encode($data, \JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

        return $encoded.'.'.$this->sign($encoded);
    }

    private function sign(string $encoded): string
    {
        return mb_substr(hash_hmac('sha256', 'click.'.$encoded, $this->secret), 0, self::SIGNATURE_LENGTH);
    }
}
