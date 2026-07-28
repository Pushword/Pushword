<?php

namespace Pushword\Newsletter\Service;

use Pushword\Core\Service\Markdown\MarkdownParser;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Contact;
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
    ): string {
        $body = $this->personalize($bodyMarkdown, $contact);

        return $this->twig->render($this->view($audience, 'email.html.twig'), [
            'audience' => $audience,
            'contact' => $contact,
            'subject' => $this->personalize($subject, $contact),
            'preheader' => null !== $preheader ? $this->personalize($preheader, $contact) : null,
            'body' => $this->markdownParser->transform($body),
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

    private function personalize(string $text, Contact $contact): string
    {
        return strtr($text, [
            '%name%' => $contact->getName(),
            '%email%' => $contact->getEmail(),
        ]);
    }
}
