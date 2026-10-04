<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Service;

use Pushword\Core\Component\EntityFilter\Filter\HtmlUnpublishedLink;
use Pushword\Core\Service\Markdown\MarkdownParser;
use Pushword\Core\Site\SiteRegistry;
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
 * Personalisation is deliberately two placeholders — `%name%` and `%email%` —
 * substituted in the subject and the body before rendering. A newsletter body is
 * authored by the site owner, but it is not a template language: anything richer
 * would mean evaluating editor input at send time.
 */
final readonly class MailRenderer
{
    public function __construct(
        private MarkdownParser $markdownParser,
        private Twig $twig,
        private SiteRegistry $siteRegistry,
        private UtmDecorator $utmDecorator,
        private ClickTracker $clickTracker,
    ) {
    }

    public function subject(string $subject, Contact $contact): string
    {
        return $this->personalize($subject, $contact);
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
    ): string {
        $body = $this->markdownParser->transform($this->personalize($bodyMarkdown, $contact));
        $body = $this->absolutize($body, $sendContext->mainHost ?? $audience->mainHost);
        // Tagging the body and not the rendered mail leaves the template's
        // unsubscribe link alone: leaving is an exit, not a visit.
        $body = $this->utmDecorator->decorate($body, $audience, $utmTag, $untrackedUrls);

        // After the UTM pass, so a recorded click still lands on a tagged URL;
        // before the template, so its own links stay out of reach. The double
        // consent gate is the tracker's to keep.
        if (null !== $trackedMail) {
            $body = $this->clickTracker->rewrite($body, $audience, $contact, $trackedMail, $untrackedUrls, $sendContext?->mainHost);
        }

        return $this->twig->render($this->view($audience, 'email.html.twig', $sendContext), [
            'audience' => $audience,
            'contact' => $contact,
            'sendContext' => $sendContext,
            'locale' => $locale ?? $contact->locale,
            'audienceName' => $sendContext->audienceName ?? $audience->name,
            'postalAddress' => null !== $sendContext ? $sendContext->postalAddress : $audience->postalAddress,
            'footer' => $this->markdownParser->transform($sendContext->footerMarkdown ?? ''),
            'subject' => $this->personalize($subject, $contact),
            'preheader' => null !== $preheader ? $this->personalize($preheader, $contact) : null,
            'body' => $body,
            'unsubscribeUrl' => $unsubscribeUrl,
        ]);
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
    public function text(Audience $audience, Contact $contact, string $bodyMarkdown, ?string $unsubscribeUrl, ?SendContext $sendContext = null): string
    {
        $postalAddress = null !== $sendContext ? $sendContext->postalAddress : $audience->postalAddress;
        $foot = array_filter([$unsubscribeUrl, $postalAddress, $sendContext?->footerMarkdown], static fn (?string $value): bool => null !== $value && '' !== $value);

        return $this->personalize($bodyMarkdown, $contact)
            .([] === $foot ? '' : "\n\n---\n".implode("\n\n", $foot)."\n");
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

    private function personalize(string $text, Contact $contact): string
    {
        return strtr($text, [
            '%name%' => $contact->name,
            '%email%' => $contact->email ?? '',
        ]);
    }
}
