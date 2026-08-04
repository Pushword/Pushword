<?php

namespace Pushword\Newsletter\Service;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\AutomationDelivery;
use Pushword\Newsletter\Entity\CampaignRecipient;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Repository\AutomationDeliveryRepository;
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
        private AutomationDeliveryRepository $deliveryRepository,
        private EnrollmentRepository $enrollmentRepository,
        private EntityManagerInterface $entityManager,
        private NewsletterMailer $mailer,
        private SiteRegistry $siteRegistry,
    ) {
    }

    /**
     * @param string[] $interests values already filtered against the audience vocabulary
     *
     * @throws InvalidArgumentException when neither an address nor a number is given
     */
    public function subscribe(
        Audience $audience,
        ?string $email = null,
        ?string $name = null,
        ?string $locale = null,
        array $interests = [],
        ?string $source = null,
        ?string $optinHost = null,
        ?string $optinIp = null,
        ?bool $requireDoubleOptIn = null,
        ?string $phone = null,
    ): Contact {
        $email = null !== $email && '' !== trim($email) ? $email : null;
        $phone = Contact::normalizePhone($phone);

        if (null === $email && null === $phone) {
            throw new InvalidArgumentException('A contact needs an email address or a phone number.');
        }

        $requireDoubleOptIn ??= $audience->requireDoubleOptIn;

        // The address identifies the person when there is one: somebody already
        // on the list who now gives their number gains it, rather than a row.
        $contact = null !== $email
            ? $this->contactRepository->findOneByEmail($audience, $email)
            : $this->contactRepository->findOneByPhone($audience, $phone);
        $isNew = ! $contact instanceof Contact;

        if ($isNew) {
            $contact = new Contact($audience, $email, $phone);
            $this->entityManager->persist($contact);
        } elseif (null !== $phone && $phone !== $contact->phone) {
            $this->assertPhoneIsFree($audience, $phone, $contact);
            $contact->phone = $phone;
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

    /**
     * A number already held by somebody else in the audience is refused, not
     * moved and not merged.
     *
     * The two rows may well be one person — that is exactly why the site is
     * adding the number. But joining them means deciding which consent record
     * survives, which token the live unsubscribe links keep working with, and
     * what happens to two ledgers of campaigns and enrollments. That is an
     * operation somebody performs deliberately, never the side effect of a
     * write that meant to fill in a field.
     *
     * @throws InvalidArgumentException
     */
    private function assertPhoneIsFree(Audience $audience, string $phone, Contact $contact): void
    {
        $holder = $this->contactRepository->findOneByPhone($audience, $phone);

        if ($holder instanceof Contact && $holder->id !== $contact->id) {
            throw new InvalidArgumentException(\sprintf('Phone %s already belongs to contact #%s in this audience.', $phone, $holder->id ?? '?'));
        }
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
        $this->attributeToLastMail($contact, 'unsub');
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
        // same row `unsubscribe()` picked: nothing was sent to them in between —
        // and if that row was a drip step, no campaign was charged to begin with.
        $last = $this->lastMailTo($contact);

        if ($last instanceof CampaignRecipient) {
            $last->campaign->decrementUnsub();
        }

        $this->entityManager->flush();
    }

    /** A permanent delivery failure: the address leaves every future segment. */
    public function markBounced(Contact $contact): void
    {
        if (null !== $contact->bouncedAt) {
            return;
        }

        $contact->markBounced();
        $this->attributeToLastMail($contact, 'bounce');
        $this->stopEnrollments($contact);
        $this->entityManager->flush();
    }

    /**
     * Credit the event to the last mail this contact actually received, so a
     * campaign's unsubscribe and bounce counts mean "caused by this send".
     *
     * That mail may be a drip step, which belongs to no campaign: the bounce is
     * then recorded on its own delivery row and nothing is charged. Charging the
     * newest campaign anyway would blame a send this person had already read
     * something else after.
     */
    private function attributeToLastMail(Contact $contact, string $kind): void
    {
        $last = $this->lastMailTo($contact);

        if (null === $last) {
            return;
        }

        $isBounce = 'bounce' === $kind;

        // Both ledgers mark their own row; only one of them has a campaign whose
        // counters the event also belongs in.
        if ($isBounce) {
            $last->markBounced();
        }

        if (! $last instanceof CampaignRecipient) {
            return;
        }

        if ($isBounce) {
            $last->campaign->incrementBounce();

            return;
        }

        $last->campaign->incrementUnsub();
    }

    /**
     * The last mail of either kind this contact actually received. Crediting an
     * event and taking it back read it the same way, or a taken-back opt-out
     * lands on another campaign than the one it was charged to.
     */
    private function lastMailTo(Contact $contact): CampaignRecipient|AutomationDelivery|null
    {
        $recipient = $this->recipientRepository->findSentFor($contact)[0] ?? null;
        $delivery = $this->deliveryRepository->findLastSentFor($contact);

        if (null === $delivery) {
            return $recipient;
        }

        if (! $recipient instanceof CampaignRecipient || null === $recipient->sentAt) {
            return $delivery;
        }

        return $delivery->attemptedAt > $recipient->sentAt ? $delivery : $recipient;
    }

    private function stopEnrollments(Contact $contact): void
    {
        foreach ($this->enrollmentRepository->findActiveFor($contact) as $enrollment) {
            $enrollment->stop();
        }
    }
}
