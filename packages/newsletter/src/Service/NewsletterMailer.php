<?php

namespace Pushword\Newsletter\Service;

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

    public function sendCampaign(Campaign $campaign, Contact $contact): void
    {
        $this->send(
            $campaign->getAudience() ?? $contact->getAudience(),
            $contact,
            $campaign->getSubject(),
            $campaign->getBodyMarkdown(),
            $campaign->getPreheader(),
            UtmTag::forCampaign($campaign),
        );
    }

    public function sendStep(AutomationStep $step, Contact $contact): void
    {
        $automation = $step->getAutomation();

        $this->send(
            $automation?->getAudience() ?? $contact->getAudience(),
            $contact,
            $step->getSubject(),
            $step->getBodyMarkdown(),
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
        $audience = $contact->getAudience();
        $confirmUrl = $this->linkGenerator->confirmUrl($contact);
        $trans = fn (string $key): string => $this->translator->trans(
            'newsletter.confirm.'.$key,
            [
                '%audience%' => $audience->getName(),
                '%host%' => $this->siteRegistry->get($audience->getMainHost())->getMainHost(),
            ],
            null,
            $contact->getLocale(),
        );
        $subject = $trans('subject');

        $email = $this->baseEmail($audience, $contact)
            ->subject($subject)
            ->text($trans('body')."\n\n".$confirmUrl."\n\n".$trans('ignore')."\n")
            ->html($this->renderer->confirmationHtml($audience, $contact, $subject, $confirmUrl));

        $this->mailer->send($email);
    }

    /** Preview a body by mailing a copy that touches no contact and no counter. */
    public function sendTest(Audience $audience, string $subject, string $bodyMarkdown, ?string $preheader, string $address, ?UtmTag $utmTag): void
    {
        $contact = new Contact($audience, $address);
        $contact->setName('Test');
        $contact->setLocale($this->siteRegistry->get($audience->getMainHost())->locale);

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
        $email = new Email()
            ->from(new Address($audience->getFromEmail(), $audience->getFromName()))
            ->to(new Address($contact->getEmail(), $contact->getName()));

        $replyTo = $audience->getReplyTo();
        if (null !== $replyTo) {
            $email->replyTo($replyTo);
        }

        return $email;
    }
}
