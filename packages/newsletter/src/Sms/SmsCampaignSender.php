<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Sms;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Entity\SmsSend;
use Pushword\Newsletter\Enum\SmsState;
use Pushword\Newsletter\Event\CampaignIssue;
use Pushword\Newsletter\Repository\CampaignRecipientRepository;
use Pushword\Newsletter\Segment\SegmentResolver;
use Pushword\Newsletter\Service\MailRenderer;
use Symfony\Component\Uid\Uuid;
use Throwable;

final readonly class SmsCampaignSender
{
    public function __construct(
        private EntityManagerInterface $em,
        private SmsSettings $settings,
        private SmsGateClient $gateway,
        private SmsQuotaLimiter $quota,
        private SegmentResolver $segments,
        private CampaignRecipientRepository $emailRecipients,
        private MailRenderer $renderer,
    ) {
    }

    /** @return list<Contact> */
    public function recipients(Campaign $campaign): array
    {
        if (! $campaign->audience instanceof Audience || ! $campaign->hasSmsAlternative()) {
            return [];
        }

        return array_values(array_filter($this->segments->smsContacts($campaign->audience, $campaign->segment), static fn (Contact $contact): bool => $contact->canReceiveSms()));
    }

    /** @return list<CampaignIssue> */
    public function issues(Campaign $campaign): array
    {
        if (! $campaign->hasSmsAlternative()) {
            return [];
        }

        if (! $this->settings->isConfigured()) {
            return [new CampaignIssue('sms.unconfigured', 'newsletter.sms.unconfigured', 'smsContent')];
        }

        $recipients = $this->recipients($campaign);
        if ([] !== $recipients && ! $this->settings->canSend()) {
            return [new CampaignIssue('sms.disabled', 'newsletter.sms.disabled', 'smsContent')];
        }

        foreach ($recipients as $contact) {
            if (! $this->validContent($this->content($campaign, $contact))) {
                return [new CampaignIssue('sms.contentInvalid', 'newsletter.sms.contentInvalid', 'smsContent')];
            }
        }

        return [];
    }

    /** Persist with the email recipients, before the first transmission. */
    public function queue(Campaign $campaign): void
    {
        foreach ($this->recipients($campaign) as $contact) {
            \assert(null !== $contact->phone);
            $this->em->persist(new SmsSend($campaign, $contact, $contact->phone, $this->content($campaign, $contact)));
        }
    }

    public function queueTest(Campaign $campaign, string $phone, string $reference): SmsSend
    {
        if (! $campaign->isDraft() || ! $this->settings->canSend() || ! Uuid::isValid($reference)
            || 1 !== preg_match('/^\+[1-9][0-9]{7,14}$/D', $phone) || ! $this->validContent($campaign->smsContent ?? '')) {
            throw new InvalidArgumentException('newsletter.sms.testInvalid');
        }

        $existing = $this->em->getRepository(SmsSend::class)->findOneBy(['reference' => $reference]);
        if (null !== $existing) {
            if (! $existing->isTest || $existing->campaign !== $campaign || $existing->phone !== $phone || $existing->content !== $campaign->smsContent) {
                throw new InvalidArgumentException('newsletter.sms.testInvalid');
            }

            return $existing;
        }

        $send = new SmsSend($campaign, null, $phone, $campaign->smsContent ?? '', true, $reference);
        $this->em->persist($send);
        $this->em->flush();

        return $send;
    }

    /** @return array{smsAccepted: int, smsFailed: int, smsSkipped: int, smsAttempts: int, smsErrors: int} */
    public function drain(int $budget, ?DateTimeImmutable $now = null): array
    {
        $report = ['smsAccepted' => 0, 'smsFailed' => 0, 'smsSkipped' => 0, 'smsAttempts' => 0, 'smsErrors' => 0];
        if (! $this->settings->canSend() || $budget < 1) {
            return $report;
        }

        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        /** @var list<SmsSend> $sends */
        $sends = $this->em->createQueryBuilder()->select('s')->from(SmsSend::class, 's')
            ->where('s.state = :state')->andWhere('s.nextAttemptAt IS NULL OR s.nextAttemptAt <= :now')
            ->setParameter('state', SmsState::Pending)->setParameter('now', $now)
            ->orderBy('s.id', 'ASC')->setMaxResults($budget)->getQuery()->getResult();
        foreach ($sends as $send) {
            if (! $this->eligible($send)) {
                $send->state = SmsState::Skipped;
                $send->nextAttemptAt = null;
                ++$report['smsSkipped'];
            } else {
                try {
                    if (! $this->quota->reserve($send, $now)) {
                        continue;
                    }

                    ++$report['smsAttempts'];
                    $send->record($this->gateway->submit($send->reference, $send->phone, $send->content), $now);
                    $outcome = match ($send->state) {
                        SmsState::Failed => 'smsFailed',
                        SmsState::Skipped => 'smsSkipped',
                        default => 'smsAccepted',
                    };
                    ++$report[$outcome];
                } catch (Throwable $error) {
                    // A timeout may follow an accepted POST. Retry the same identity and keep its quota slot.
                    ++$report['smsErrors'];
                    $send->error = $error::class;
                    $send->nextAttemptAt = $now->modify('+5 minutes');
                }
            }

            $this->em->flush();
            $this->complete($send->campaign);
        }

        return $report;
    }

    public function hasPending(Campaign $campaign): bool
    {
        return 0 < $this->em->getRepository(SmsSend::class)->count(['campaign' => $campaign, 'isTest' => false, 'state' => SmsState::Pending]);
    }

    public function refresh(Campaign $campaign): void
    {
        foreach ($this->em->getRepository(SmsSend::class)->findBy(['campaign' => $campaign]) as $send) {
            if (! \in_array($send->state, [SmsState::Accepted, SmsState::Sent], true)) {
                continue;
            }

            $state = $this->gateway->status($send->reference);
            if (null !== $state) {
                $send->record($state, new DateTimeImmutable('now', new DateTimeZone('UTC')));
                $this->em->flush();
            }
        }
    }

    public function cancel(Campaign $campaign): void
    {
        foreach ($this->em->getRepository(SmsSend::class)->findBy(['campaign' => $campaign, 'state' => SmsState::Pending]) as $send) {
            $send->state = SmsState::Skipped;
            $send->nextAttemptAt = null;
        }

        $this->em->flush();
        $this->complete($campaign);
    }

    /** @return array<string, int> */
    public function counts(Campaign $campaign): array
    {
        $counts = array_fill_keys(array_column(SmsState::cases(), 'value'), 0);
        foreach ($this->em->getRepository(SmsSend::class)->findBy(['campaign' => $campaign, 'isTest' => false]) as $send) {
            ++$counts[$send->state->value];
        }

        return $counts;
    }

    private function eligible(SmsSend $send): bool
    {
        if (! $send->campaign instanceof Campaign) {
            return false;
        }

        if ($send->isTest) {
            return true;
        }

        $contact = $send->contact;

        return $send->campaign->isSending() && $contact instanceof Contact && $contact->canReceiveSms()
            && null === $contact->email && $contact->phone === $send->phone && $contact->audience === $send->campaign->audience
            && $this->segments->matchesSms($contact, $send->campaign->segment);
    }

    private function complete(?Campaign $campaign): void
    {
        if ($campaign instanceof Campaign && $campaign->isSending() && ! $this->hasPending($campaign) && 0 === $this->emailRecipients->countPending($campaign)) {
            $campaign->markSent();
            $this->em->flush();
        }
    }

    private function content(Campaign $campaign, Contact $contact): string
    {
        return $this->renderer->subject($campaign->smsContentFor($contact->locale), $contact);
    }

    private function validContent(string $content): bool
    {
        return '' !== trim($content) && mb_strlen($content) <= 480;
    }
}
