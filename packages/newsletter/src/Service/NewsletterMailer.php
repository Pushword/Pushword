<?php

namespace Pushword\Newsletter\Service;

use LogicException;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\AutomationStep;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Utm\UtmTag;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Builds and hands one mail to the transport.
 *
 * Every message a subscribed contact receives carries `List-Unsubscribe` and
 * RFC 8058 one-click, so unsubscribing never depends on the recipient finding
 * the link in the body — and inboxes stop treating the mail as unattributed bulk.
 */
final readonly class NewsletterMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private MailRenderer $renderer,
        private LinkGenerator $linkGenerator,
        private SiteRegistry $siteRegistry,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * The body is resolved here rather than frozen when the campaign was armed:
     * a recipient row records *who*, never *what*. Freezing the rendered text
     * per recipient would multiply the ledger by the body size for no gain, and
     * would make fixing a typo mid-campaign impossible.
     */
    public function sendCampaign(Campaign $campaign, Contact $contact): void
    {
        $content = $campaign->contentFor($contact->locale);

        $this->send(
            $campaign->audience ?? $contact->audience,
            $contact,
            $content['subject'],
            $content['bodyMarkdown'],
            $content['preheader'],
            UtmTag::forCampaign($campaign),
        );
    }

    /**
     * The subject and the body arrive already rendered: what a step quotes comes
     * from the occurrence that enrolled this contact, which the caller holds and
     * this does not.
     */
    public function sendStep(AutomationStep $step, Contact $contact, string $subject, string $bodyMarkdown): void
    {
        $automation = $step->automation;

        $this->send(
            $automation->audience ?? $contact->audience,
            $contact,
            $subject,
            $bodyMarkdown,
            null,
            null !== $automation ? UtmTag::forStep($automation, $step) : null,
        );
    }

    /**
     * The double opt-in mail. It carries no unsubscribe header on purpose: the
     * contact is not subscribed yet, and doing nothing is already the opt-out.
     */
    public function sendConfirmation(Contact $contact): void
    {
        $audience = $contact->audience;
        $confirmUrl = $this->linkGenerator->confirmUrl($contact);
        $trans = fn (string $key): string => $this->translator->trans(
            'newsletter.confirm.'.$key,
            [
                '%audience%' => $audience->name,
                '%host%' => $this->siteRegistry->get($audience->mainHost)->getMainHost(),
            ],
            null,
            $contact->locale,
        );
        $subject = $trans('subject');

        $email = $this->baseEmail($audience, $contact)
            ->subject($subject)
            ->text($trans('body')."\n\n".$confirmUrl."\n\n".$trans('ignore')."\n")
            ->html($this->renderer->confirmationHtml($audience, $contact, $subject, $confirmUrl));

        $this->mailer->send($email);
    }

    /**
     * Preview a body by mailing a copy that touches no contact and no counter.
     *
     * The locale is what the test contact carries, so a translation is proofread
     * the way its readers will receive it — the chassis around the body is
     * translated in it too. Empty means the audience host's own language.
     */
    public function sendTest(Audience $audience, string $subject, string $bodyMarkdown, ?string $preheader, string $address, ?UtmTag $utmTag, string $locale = ''): void
    {
        $contact = new Contact($audience, $address);
        $contact->name = 'Test';
        $contact->locale = '' !== trim($locale) ? $locale : $this->siteRegistry->get($audience->mainHost)->locale;

        // A test recipient has no persisted token, so the unsubscribe link would
        // 404. Point it at the site instead — the point is to read the body.
        $unsubscribeUrl = $this->linkGenerator->base($audience).'/';

        $email = $this->baseEmail($audience, $contact)
            ->subject('[TEST] '.$this->renderer->subject($subject, $contact))
            ->text($this->renderer->text($contact, $bodyMarkdown, $unsubscribeUrl))
            ->html($this->renderer->html($audience, $contact, $subject, $bodyMarkdown, $preheader, $unsubscribeUrl, $utmTag));

        $this->mailer->send($email);
    }

    private function send(Audience $audience, Contact $contact, string $subject, string $bodyMarkdown, ?string $preheader, ?UtmTag $utmTag): void
    {
        $unsubscribeUrl = $this->linkGenerator->unsubscribeUrl($contact);

        $email = $this->baseEmail($audience, $contact)
            ->subject($this->renderer->subject($subject, $contact))
            ->text($this->renderer->text($contact, $bodyMarkdown, $unsubscribeUrl))
            ->html($this->renderer->html($audience, $contact, $subject, $bodyMarkdown, $preheader, $unsubscribeUrl, $utmTag));

        $headers = $email->getHeaders();
        $headers->addTextHeader('List-Unsubscribe', '<'.$unsubscribeUrl.'>');
        $headers->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');

        $this->mailer->send($email);
    }

    private function baseEmail(Audience $audience, Contact $contact): Email
    {
        // Whoever got here already asked `isMailable()`; saying which contact
        // slipped through beats a TypeError on a null address.
        $to = $contact->email ?? throw new LogicException('Contact #'.($contact->id ?? '?').' has no email address.');

        $email = new Email()
            ->from(new Address($audience->fromEmail, $audience->fromName))
            ->to(new Address($to, $contact->name));

        $replyTo = $audience->replyTo;
        if (null !== $replyTo) {
            $email->replyTo($replyTo);
        }

        return $email;
    }
}
