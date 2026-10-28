<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Service;

use DateTimeImmutable;
use Pushword\Core\Component\EntityFilter\Filter\HtmlUnpublishedLink;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Newsletter\Block\BlockMarkup;
use Pushword\Newsletter\Click\ClickTracker;
use Pushword\Newsletter\Delivery\SendContext;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\AutomationStep;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Utm\UtmDecorator;
use Pushword\Newsletter\Utm\UtmTag;
use Twig\Environment as Twig;

/**
 * Turns a campaign or step body into the two parts of a mail.
 *
 * Contact personalisation uses `%name%`, `%email%` and `%firstName|fallback%`,
 * substituted in the subject and the body before rendering. A newsletter body is
 * authored by the site owner, but it is not a template language: anything richer
 * would mean evaluating editor input at send time.
 */
final readonly class MailRenderer
{
    public function __construct(
        private EmailMarkdownRenderer $markdownParser,
        private Twig $twig,
        private SiteRegistry $siteRegistry,
        private UtmDecorator $utmDecorator,
        private ClickTracker $clickTracker,
        private EmailTemplateRegistry $emailTemplates,
    ) {
    }

    public function subject(string $subject, Contact $contact): string
    {
        return $this->personalize($subject, $contact);
    }

    /** @return list<string> */
    public function trackingScopes(): array
    {
        return $this->clickTracker->scopes();
    }

    /** @param list<string> $untrackedUrls */
    public function html(
        Audience $audience,
        Contact $contact,
        string $subject,
        string $bodyMarkdown,
        ?string $preheader,
        ?string $unsubscribeUrl,
        ?UtmTag $utmTag,
        Campaign|AutomationStep|null $trackedMail = null,
        ?SendContext $sendContext = null,
        ?string $locale = null,
        array $untrackedUrls = [],
        ?string $emailLayout = null,
        ?string $messageReference = null,
    ): string {
        $bodyMarkdown = BlockMarkup::expand($bodyMarkdown);
        $host = $sendContext->mainHost ?? $audience->mainHost;
        if ('personal' !== ($emailLayout ?? $audience->emailLayout) && null !== $sendContext?->signatureMarkdown) {
            $bodyMarkdown .= "\n\n".$this->designText($sendContext->signatureMarkdown, $sendContext->mainHost);
        }

        $body = $this->markdownParser->transform($this->personalize($bodyMarkdown, $contact, markdown: true), $host);
        $body = $this->absolutize($body, $host);
        // The body is instrumented first; explicitly marked template roles follow below.
        $body = $this->utmDecorator->decorate($body, $audience, $utmTag, $untrackedUrls);

        // After the UTM pass, so a recorded click still lands on a tagged URL;
        // before the template, whose unmarked links remain excluded. The double
        // consent gate is the tracker's to keep.
        if (null !== $trackedMail) {
            $body = $this->clickTracker->rewrite($body, $audience, $contact, $trackedMail, $untrackedUrls, $sendContext?->mainHost, $sendContext?->systemLinkBaseUrl, $messageReference);
        }

        $layout = $emailLayout ?? $audience->emailLayout;
        if ('personal' === $layout) {
            $signature = $this->designText($sendContext->signatureMarkdown ?? $audience->signatureMarkdown, $host);

            $html = $this->twig->render($this->view($audience, 'personal.email.html.twig', $sendContext), [
                'locale' => $locale ?? $contact->locale,
                'subject' => $this->personalize($subject, $contact),
                'body' => $body,
                'unsubscribeUrl' => $unsubscribeUrl,
                'postalAddress' => $sendContext instanceof SendContext ? $sendContext->postalAddress : $audience->postalAddress,
                'signature' => $this->markScope($this->absolutize($this->markdownParser->transform($signature, $host), $host), 'footer'),
            ]);

            return $this->templateLinks($html, $audience, $contact, $trackedMail, $utmTag, $sendContext, $untrackedUrls, $unsubscribeUrl, $messageReference);
        }

        $saved = $this->emailTemplates->saved($layout);
        $template = str_starts_with($layout, 'twig:')
            ? $this->emailTemplates->template(substr($layout, 5))
            : $this->view($audience, 'email.html.twig', $sendContext);
        $header = $this->designText($saved->headerMarkdown ?? $audience->headerMarkdown, $host);
        $footer = $this->designText($sendContext->footerMarkdown ?? $saved->footerMarkdown ?? $audience->footerMarkdown, $host);

        $html = $this->twig->render($template, [
            'audience' => $audience,
            'contact' => $contact,
            'sendContext' => $sendContext,
            'locale' => $locale ?? $contact->locale,
            'audienceName' => $sendContext->audienceName ?? $audience->name,
            'siteName' => $this->siteRegistry->get($host)->name,
            'year' => new DateTimeImmutable()->format('Y'),
            'postalAddress' => $sendContext instanceof SendContext ? $sendContext->postalAddress : $audience->postalAddress,
            'wrapped' => $saved->wrapped ?? 'framed' === $layout,
            'header' => $this->markScope($this->absolutize($this->markdownParser->transform($header, $host), $host), 'navigation'),
            'footer' => $this->markScope($this->absolutize($this->markdownParser->transform($footer, $host), $host), 'footer'),
            'subject' => $this->personalize($subject, $contact),
            'preheader' => null !== $preheader ? $this->personalize($preheader, $contact) : null,
            'body' => $body,
            'unsubscribeUrl' => $unsubscribeUrl,
        ]);

        return $this->templateLinks($html, $audience, $contact, $trackedMail, $utmTag, $sendContext, $untrackedUrls, $unsubscribeUrl, $messageReference);
    }

    /** @param list<string> $untrackedUrls */
    private function templateLinks(string $html, Audience $audience, Contact $contact, Campaign|AutomationStep|null $mail, ?UtmTag $utmTag, ?SendContext $context, array $untrackedUrls, ?string $unsubscribeUrl, ?string $reference): string
    {
        if (null !== $unsubscribeUrl) {
            $untrackedUrls[] = $unsubscribeUrl;
        }

        $html = $this->utmDecorator->decorate($html, $audience, $utmTag, $untrackedUrls, $this->clickTracker->scopes());

        return null === $mail ? $html : $this->clickTracker->rewrite($html, $audience, $contact, $mail, $untrackedUrls, $context?->mainHost, $context?->systemLinkBaseUrl, $reference, 'unknown');
    }

    private function markScope(string $html, string $scope): string
    {
        return preg_replace_callback(HtmlUnpublishedLink::HTML_REGEX, static fn (array $match): string => 1 !== preg_match('/\sdata-newsletter-scope\s*=/i', $match['before'].$match['after'])
            ? '<a data-newsletter-scope="'.$scope.'"'.substr($match[0], 2) : $match[0], $html) ?? $html;
    }

    /** @param string|null $confirmTrackingUrl the confirm-and-accept-tracking variant, when the audience asks for the consent */
    public function confirmationHtml(Audience $audience, Contact $contact, string $subject, string $confirmUrl, ?string $confirmTrackingUrl = null): string
    {
        return $this->twig->render($this->view($audience, 'confirm.email.html.twig'), [
            'audience' => $audience,
            'contact' => $contact,
            'subject' => $subject,
            'confirmUrl' => $confirmUrl,
            'confirmTrackingUrl' => $confirmTrackingUrl,
            'primaryColor' => $this->siteRegistry->get($audience->mainHost)
                ->getStr('css_var:color_primary', '#1c1c1c'),
        ]);
    }

    /**
     * The Markdown source doubles as the plain-text part: it is already written
     * to be read raw. It carries the same foot as the HTML one — a reader whose
     * client shows them the text part is owed the address just as much.
     *
     * A null `$unsubscribeUrl` is a transactional mail, which offers no way off
     * a list it did not put anybody on; the postal address is unaffected, since
     * it says who wrote rather than how to leave.
     */
    public function text(Audience $audience, Contact $contact, string $bodyMarkdown, ?string $unsubscribeUrl, ?SendContext $sendContext = null, ?string $emailLayout = null): string
    {
        $bodyMarkdown = BlockMarkup::expand($bodyMarkdown);
        $layout = $emailLayout ?? $audience->emailLayout;
        if ('personal' === $layout) {
            $signature = $this->designText($sendContext->signatureMarkdown ?? $audience->signatureMarkdown, $sendContext->mainHost ?? $audience->mainHost);

            $foot = array_filter([$unsubscribeUrl, $sendContext instanceof SendContext ? $sendContext->postalAddress : $audience->postalAddress], static fn (?string $value): bool => null !== $value && '' !== $value);

            return $this->personalize($bodyMarkdown, $contact).('' === $signature ? '' : "\n\n".$signature)
                .([] === $foot ? '' : "\n\n".implode("\n", $foot));
        }

        if (null !== $sendContext?->signatureMarkdown) {
            $bodyMarkdown .= "\n\n".$this->designText($sendContext->signatureMarkdown, $sendContext->mainHost);
        }

        $postalAddress = $sendContext instanceof SendContext ? $sendContext->postalAddress : $audience->postalAddress;
        $saved = $this->emailTemplates->saved($layout);
        $custom = str_starts_with($layout, 'twig:');
        $header = $custom ? '' : ($saved->headerMarkdown ?? $audience->headerMarkdown);
        $footer = $custom ? '' : ($saved->footerMarkdown ?? $audience->footerMarkdown);
        $foot = array_filter(
            [$unsubscribeUrl, $postalAddress, $sendContext->footerMarkdown ?? $footer],
            static fn (?string $value): bool => null !== $value && '' !== $value,
        );

        $host = $sendContext->mainHost ?? $audience->mainHost;

        return ('' === $header ? '' : $this->designText($header, $host)."\n\n")
            .$this->personalize($bodyMarkdown, $contact)
            .([] === $foot ? '' : "\n\n---\n".$this->designText(implode("\n\n", $foot), $host)."\n");
    }

    private function designText(string $text, string $host): string
    {
        return strtr($text, [
            '%siteName%' => $this->siteRegistry->get($host)->name,
            '%year%' => new DateTimeImmutable()->format('Y'),
        ]);
    }

    /** Resolve a template, letting the site override the bundle's default. */
    public function view(Audience $audience, string $template, ?SendContext $sendContext = null): string
    {
        return $this->siteRegistry->get($sendContext->mainHost ?? $audience->mainHost)
            ->getView('/newsletter/'.$template, '@PushwordNewsletter');
    }

    /**
     * A root-relative link is dead in an inbox: there is no page to resolve it
     * against. They are bound to the site's canonical base rather than to its
     * live origin — the reader should land on the published page, not on the
     * machine the mail happened to leave from.
     */
    private function absolutize(string $html, string $mainHost): string
    {
        if (! str_contains($html, '<a ')) {
            return $html;
        }

        $base = rtrim($this->siteRegistry->get($mainHost)->baseUrl, '/');

        return preg_replace_callback(
            HtmlUnpublishedLink::HTML_REGEX,
            static function (array $match) use ($base): string {
                // `//host/path` only looks root-relative: it is already absolute.
                $isRootRelative = str_starts_with($match['href'], '/') && ! str_starts_with($match['href'], '//');

                if (! $isRootRelative) {
                    return $match[0];
                }

                return '<a'.$match['before'].'href='.$match['quote'].$base.$match['href'].$match['quote']
                    .$match['after'].'>'.$match['content'].'</a>';
            },
            $html
        ) ?? $html;
    }

    private function personalize(string $text, Contact $contact, bool $markdown = false): string
    {
        return preg_replace_callback('/%name%|%email%|%firstName(?:\|([^%]*))?%/', static function (array $match) use ($contact, $markdown): string {
            if ('%name%' === $match[0]) {
                return $contact->name;
            }

            if ('%email%' === $match[0]) {
                return $contact->email ?? '';
            }

            $firstName = $contact->customProperties['firstName'] ?? null;
            $value = \is_string($firstName) ? trim($firstName) : '';
            $value = '' === $value ? ($match[1] ?? '') : $value;

            // A contact property is text, never authored Markdown or HTML.
            return $markdown ? htmlspecialchars(addcslashes($value, '\\`*_{}[]()#+-.!|'), \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8') : $value;
        }, $text) ?? $text;
    }
}
