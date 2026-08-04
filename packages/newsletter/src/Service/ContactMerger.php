<?php

namespace Pushword\Newsletter\Service;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Repository\AutomationDeliveryRepository;
use Pushword\Newsletter\Repository\CampaignRecipientRepository;
use Pushword\Newsletter\Repository\EnrollmentRepository;

/**
 * Join two rows that turn out to be one person: somebody the site knew by phone
 * who gives their address, or the reverse.
 *
 * **The row holding the address is the one that survives**, whichever side the
 * write came from. That is not a preference for mail: the address is what the
 * unsubscribe and confirm links are keyed on, so keeping the other row would
 * silently invalidate every link already in somebody's mailbox — and the consent
 * ledger the survivor carries (when, from where, from which IP) is the record
 * that has to be produced if the opt-in is ever questioned.
 *
 * It follows that a merge is only ever offered between an addressed row and a
 * phone-only one. Two addresses are two people until somebody says otherwise,
 * and no rule here can say which of the two consent records to throw away.
 */
final readonly class ContactMerger
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CampaignRecipientRepository $recipientRepository,
        private EnrollmentRepository $enrollmentRepository,
        private AutomationDeliveryRepository $deliveryRepository,
    ) {
    }

    /**
     * Which of the two rows a merge would keep — null when there is no merge to
     * offer. What a screen asks before drawing the button.
     */
    public function keeper(Contact $left, Contact $right): ?Contact
    {
        try {
            return $this->resolve($left, $right)[0];
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * @return Contact the surviving row, which may not be the one passed first
     *
     * @throws InvalidArgumentException when the two rows are not two halves of one person
     */
    public function merge(Contact $left, Contact $right): Contact
    {
        [$keeper, $absorbed] = $this->resolve($left, $right);

        $phone = $absorbed->phone;

        $this->moveLedger($absorbed, $keeper);
        $this->fill($keeper, $absorbed);

        // The number changes hands in a second flush. Doctrine writes updates
        // before deletes, so a keeper taking it in one go would meet the unique
        // index while the row still holding it is there. Clearing it first also
        // settles the case of a caller whose refused write is already sitting on
        // the keeper, unflushed.
        $keeper->phone = null;

        $this->entityManager->remove($absorbed);
        $this->entityManager->flush();

        $keeper->phone = $phone;
        $this->entityManager->flush();

        return $keeper;
    }

    /**
     * @return array{Contact, Contact} the survivor, then the row that disappears
     *
     * @throws InvalidArgumentException
     */
    private function resolve(Contact $left, Contact $right): array
    {
        if ($left === $right) {
            throw new InvalidArgumentException('A contact cannot be merged with itself.');
        }

        if ($left->audience->id !== $right->audience->id) {
            throw new InvalidArgumentException('Two rows on different lists are two subscriptions, each with its own consent.');
        }

        $addressed = array_values(array_filter([$left, $right], static fn (Contact $contact): bool => null !== $contact->email));

        if (1 !== \count($addressed)) {
            throw new InvalidArgumentException('A merge keeps the address and the links keyed on it, so exactly one of the two rows may hold one.');
        }

        $keeper = $addressed[0];
        $absorbed = $keeper === $left ? $right : $left;

        // Refused rather than arbitrated: one of the two numbers would go, and
        // nothing here knows which one the site still calls.
        if (null !== $keeper->phone && $keeper->phone !== $absorbed->phone) {
            throw new InvalidArgumentException(\sprintf('Contact #%s already holds another number.', $keeper->id ?? '?'));
        }

        return [$keeper, $absorbed];
    }

    /**
     * Campaigns sent, drips in progress and steps delivered follow the person,
     * so a merge costs no history — that is what makes it something other than
     * deleting one of the two rows.
     *
     * A row the keeper already has is dropped instead of moved: both unique keys
     * ({@see CampaignRecipient}'s campaign, {@see Enrollment}'s automation and
     * subject) name something that happened once. Dropping it is explicit rather
     * than left to the database cascade — a row still pointing at a contact
     * being deleted is what Doctrine refuses to flush.
     */
    private function moveLedger(Contact $absorbed, Contact $keeper): void
    {
        foreach ($this->recipientRepository->findBy(['contact' => $absorbed]) as $recipient) {
            if (null === $this->recipientRepository->findOneBy(['campaign' => $recipient->campaign, 'contact' => $keeper])) {
                $recipient->moveTo($keeper);

                continue;
            }

            $this->entityManager->remove($recipient);
        }

        foreach ($this->enrollmentRepository->findBy(['contact' => $absorbed]) as $enrollment) {
            $twin = $this->enrollmentRepository->findOneBy([
                'contact' => $keeper,
                'automation' => $enrollment->automation,
                'subjectId' => $enrollment->subjectId,
            ]);

            if (null === $twin) {
                $enrollment->moveTo($keeper);

                continue;
            }

            $this->entityManager->remove($enrollment);
        }

        // Nothing keeps a contact from being sent the same step twice — a drip
        // resolves its recipient when it sends — so every row moves.
        foreach ($this->deliveryRepository->findBy(['contact' => $absorbed]) as $delivery) {
            $delivery->moveTo($keeper);
        }
    }

    /**
     * What the absorbed row knew and the keeper did not.
     *
     * Only the blanks are filled: the keeper's own name and language were
     * written by somebody, and a merge is not the moment to overrule them. The
     * consent fields are not among these — provenance belongs to the opt-in it
     * describes, and copying another row's would forge it.
     */
    private function fill(Contact $keeper, Contact $absorbed): void
    {
        if ('' === $keeper->name) {
            $keeper->name = $absorbed->name;
        }

        if ('' === $keeper->locale) {
            $keeper->locale = $absorbed->locale;
        }

        // Interests add up: a tag says what the person is on the list for, and
        // both rows were the same person.
        foreach ($absorbed->getTagList() as $tag) {
            $keeper->addTag($tag);
        }

        foreach ($absorbed->customProperties as $key => $value) {
            if (! $keeper->hasCustomProperty((string) $key)) {
                $keeper->setCustomProperty((string) $key, $value);
            }
        }
    }
}
