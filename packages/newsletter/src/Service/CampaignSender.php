<?php

namespace Pushword\Newsletter\Service;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Entity\CampaignRecipient;
use Pushword\Newsletter\Repository\CampaignRecipientRepository;
use Pushword\Newsletter\Segment\SegmentResolver;
use Throwable;

/**
 * Materialises a campaign's recipients, then drains them at the configured
 * cadence.
 *
 * Pacing is derived from the last mail actually sent rather than from a sleep:
 * the tick can therefore return immediately, and a campaign resumes at the right
 * rate whether the previous run finished, crashed or was killed mid-drain.
 */
final readonly class CampaignSender
{
    public function __construct(
        private SegmentResolver $segmentResolver,
        private CampaignRecipientRepository $recipientRepository,
        private EntityManagerInterface $entityManager,
        private NewsletterMailer $mailer,
    ) {
    }

    /**
     * Freeze the audience into recipient rows and open the send.
     *
     * @return int the number of recipients the campaign will reach
     */
    public function arm(Campaign $campaign): int
    {
        $audience = $campaign->getAudience() ?? throw new LogicException('Campaign has no audience.');

        if (! $campaign->isDraft() && ! $campaign->isScheduled()) {
            throw new LogicException('Only a draft or scheduled campaign can be armed.');
        }

        $already = array_flip($this->recipientRepository->contactIds($campaign));
        $count = 0;

        foreach ($this->segmentResolver->contacts($audience, $campaign->getSegment()) as $contact) {
            if (null !== $contact->id && isset($already[$contact->id])) {
                continue;
            }

            $this->entityManager->persist(new CampaignRecipient($campaign, $contact));
            ++$count;
        }

        $campaign->markSending($count + \count($already));
        $this->entityManager->flush();

        return $campaign->getRecipientCount();
    }

    /**
     * Send what the cadence allows, up to `$budget` mails.
     *
     * @return int the number of mails handed to the transport
     */
    public function drain(Campaign $campaign, int $budget): int
    {
        if ($budget < 1 || ! $campaign->isSending()) {
            return 0;
        }

        $allowance = min($budget, $this->allowance($campaign));
        if ($allowance < 1) {
            return 0;
        }

        $sent = 0;
        foreach ($this->recipientRepository->findPending($campaign, $allowance) as $recipient) {
            $contact = $recipient->getContact();

            // Consent can change between arming and sending; the ledger records
            // that we chose not to send, which is not a delivery failure.
            if (! $contact->isSubscribed()) {
                $recipient->markSkipped();

                continue;
            }

            try {
                $this->mailer->sendCampaign($campaign, $contact);
                $recipient->markSent();
                $campaign->incrementSent();
                ++$sent;
            } catch (Throwable $throwable) {
                $recipient->markFailed($throwable->getMessage());
                $campaign->incrementFailed();
            }
        }

        $this->entityManager->flush();

        if (0 === $this->recipientRepository->countPending($campaign)) {
            $campaign->markSent();
            $this->entityManager->flush();
        }

        return $sent;
    }

    /**
     * How many mails the cadence permits right now. The first mail of a campaign
     * goes out alone: without a previous send there is no elapsed time to divide,
     * and one mail is what the cadence would have allowed anyway.
     */
    private function allowance(Campaign $campaign): int
    {
        $last = $this->recipientRepository->lastSentAt($campaign);

        if (! $last instanceof DateTimeImmutable) {
            return 1;
        }

        $elapsed = time() - $last->getTimestamp();

        return max(0, intdiv($elapsed, $campaign->getEffectiveRateSeconds()));
    }
}
