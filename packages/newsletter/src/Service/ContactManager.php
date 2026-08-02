<?php

namespace Pushword\Newsletter\Service;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\CampaignRecipient;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Repository\CampaignRecipientRepository;
use Pushword\Newsletter\Repository\ContactRepository;
use Pushword\Newsletter\Repository\EnrollmentRepository;

/**
 * The single place a contact's consent changes.
 *
 * Subscribing is idempotent: a second submission of an address already on the
 * list updates what it knows about the person and never re-opens a confirmation
 * they already answered. Leaving is terminal until the person opts in again.
 */
final readonly class ContactManager
{
    public function __construct(
        private ContactRepository $contactRepository,
        private CampaignRecipientRepository $recipientRepository,
        private EnrollmentRepository $enrollmentRepository,
        private EntityManagerInterface $entityManager,
        private NewsletterMailer $mailer,
        private SiteRegistry $siteRegistry,
    ) {
    }

    /**
     * @param string[] $interests values already filtered against the audience vocabulary
     */
    public function subscribe(
        Audience $audience,
        string $email,
        ?string $name = null,
        ?string $locale = null,
        array $interests = [],
        ?string $source = null,
        ?string $optinHost = null,
        ?string $optinIp = null,
        ?bool $requireDoubleOptIn = null,
    ): Contact {
        $requireDoubleOptIn ??= $audience->requireDoubleOptIn;
        $contact = $this->contactRepository->findOneByEmail($audience, $email);
        $isNew = ! $contact instanceof Contact;

        if ($isNew) {
            $contact = new Contact($audience, $email);
            $this->entityManager->persist($contact);
        }

        if (null !== $name && '' !== trim($name)) {
            $contact->name = $name;
        }

        if (null !== $locale && '' !== trim($locale)) {
            $contact->locale = $locale;
        }

        // Nothing said which language this person reads — an API import rarely
        // knows. The audience's host answers it, the way a page takes its locale
        // from the host it belongs to.
        if ('' === $contact->locale) {
            $contact->locale = $this->siteRegistry->get($audience->mainHost)->locale;
        }

        foreach ($interests as $interest) {
            $contact->addTag($interest);
        }

        // Provenance is written on the first opt-in and on any re-opt-in, never
        // on a repeat submission by someone already subscribed: the evidence
        // must point at the moment consent was actually given.
        $reopening = $isNew || ! $contact->isSubscribed();
        if ($reopening) {
            $contact->source = $source;
            $contact->optinHost = $optinHost;
            $contact->optinIp = $optinIp;
            $contact->optIn($requireDoubleOptIn);
        }

        // Send before flushing: a transport refusing the address (a typo most
        // often) must not leave a pending contact no confirmation ever reached.
        if ($reopening && $contact->isPending()) {
            $this->mailer->sendConfirmation($contact);
        }

        $this->entityManager->flush();

        return $contact;
    }

    public function confirm(Contact $contact): void
    {
        if (! $contact->isPending()) {
            return;
        }

        $contact->confirm();
        $this->entityManager->flush();
    }

    public function unsubscribe(Contact $contact): void
    {
        if (null !== $contact->unsubscribedAt) {
            return;
        }

        $contact->unsubscribe();
        $this->attributeToLastCampaign($contact, 'unsub');
        $this->stopEnrollments($contact);
        $this->entityManager->flush();
    }

    /**
     * Take an opt-out back, from the person's own unsubscribe link.
     *
     * No confirmation mail stands in the way: the token reached them by mail and
     * nowhere else, so the mailbox is already proven — making them prove it again
     * to undo a click they just made would be theatre. It is also what lets the
     * opt-out itself cost one click.
     *
     * A bounced address is not revived this way. The mail server refused it; a
     * click says nothing about that.
     */
    public function resubscribe(Contact $contact): void
    {
        if (null === $contact->unsubscribedAt || null !== $contact->bouncedAt) {
            return;
        }

        $contact->optIn(false);

        // The campaign credited with the opt-out gets its count back. It is the
        // same row `unsubscribe()` picked: nothing was sent to them in between.
        $this->lastSentTo($contact)?->campaign->decrementUnsub();

        $this->entityManager->flush();
    }

    /** A permanent delivery failure: the address leaves every future segment. */
    public function markBounced(Contact $contact): void
    {
        if (null !== $contact->bouncedAt) {
            return;
        }

        $contact->markBounced();
        $this->attributeToLastCampaign($contact, 'bounce');
        $this->stopEnrollments($contact);
        $this->entityManager->flush();
    }

    /**
     * Credit the event to the last campaign this contact actually received, so a
     * campaign's unsubscribe and bounce counts mean "caused by this send".
     */
    private function attributeToLastCampaign(Contact $contact, string $kind): void
    {
        $last = $this->lastSentTo($contact);

        if (null === $last) {
            return;
        }

        if ('bounce' === $kind) {
            $last->markBounced();
            $last->campaign->incrementBounce();

            return;
        }

        $last->campaign->incrementUnsub();
    }

    /**
     * The last campaign this contact actually received. Crediting an event and
     * taking it back read it the same way, or a taken-back opt-out lands on
     * another campaign than the one it was charged to.
     */
    private function lastSentTo(Contact $contact): ?CampaignRecipient
    {
        return $this->recipientRepository->findSentFor($contact)[0] ?? null;
    }

    private function stopEnrollments(Contact $contact): void
    {
        foreach ($this->enrollmentRepository->findActiveFor($contact) as $enrollment) {
            $enrollment->stop();
        }
    }
}
