<?php

namespace Pushword\Newsletter\Service;

use Pushword\Core\Component\EntityFilter\Filter\HtmlUnpublishedLink;
use Pushword\Core\Service\Markdown\MarkdownParser;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Newsletter\Entity\Audience;
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
    ) {
    }

    public function subject(string $subject, Contact $contact): string
    {
        return $this->personalize($subject, $contact);
    }

    public function html(
        Audience $audience,
        Contact $contact,
        string $subject,
        string $bodyMarkdown,
        ?string $preheader,
        string $unsubscribeUrl,
        ?UtmTag $utmTag,
    ): string {
        $body = $this->markdownParser->transform($this->personalize($bodyMarkdown, $contact));
        $body = $this->absolutize($body, $audience);

        return $this->twig->render($this->view($audience, 'email.html.twig'), [
            'audience' => $audience,
            'contact' => $contact,
            'subject' => $this->personalize($subject, $contact),
            'preheader' => null !== $preheader ? $this->personalize($preheader, $contact) : null,
            // Only the body is tagged: the unsubscribe link the template adds is
            // an exit, not a visit, and its header twin could not be tagged anyway.
            'body' => $this->utmDecorator->decorate($body, $audience, $utmTag),
            'unsubscribeUrl' => $unsubscribeUrl,
        ]);
    }

    public function confirmationHtml(Audience $audience, Contact $contact, string $subject, string $confirmUrl): string
    {
        return $this->twig->render($this->view($audience, 'confirm.email.html.twig'), [
            'audience' => $audience,
            'contact' => $contact,
            'subject' => $subject,
            'confirmUrl' => $confirmUrl,
        ]);
    }

    /** The Markdown source doubles as the plain-text part: it is already written to be read raw. */
    public function text(Contact $contact, string $bodyMarkdown, string $unsubscribeUrl): string
    {
        return $this->personalize($bodyMarkdown, $contact)."\n\n---\n".$unsubscribeUrl."\n";
    }

    /** Resolve a template, letting the site override the bundle's default. */
    public function view(Audience $audience, string $template): string
    {
        return $this->siteRegistry->get($audience->getMainHost())
            ->getView('/newsletter/'.$template, '@PushwordNewsletter');
    }

    /**
     * A root-relative link is dead in an inbox: there is no page to resolve it
     * against. They are bound to the site's canonical base rather than to its
     * live origin — the reader should land on the published page, not on the
     * machine the mail happened to leave from.
     */
    private function absolutize(string $html, Audience $audience): string
    {
        if (! str_contains($html, '<a ')) {
            return $html;
        }

        $base = rtrim($this->siteRegistry->get($audience->getMainHost())->getBaseUrl(), '/');

        return preg_replace_callback(
            HtmlUnpublishedLink::HTML_REGEX,
            static function (array $match) use ($base): string {
                // `//host/path` is already absolute, protocol-relative.
                if (! str_starts_with($match['href'], '/') || str_starts_with($match['href'], '//')) {
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
            '%name%' => $contact->getName(),
            '%email%' => $contact->getEmail(),
        ]);
    }
}
