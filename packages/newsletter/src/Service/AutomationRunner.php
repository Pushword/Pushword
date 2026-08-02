<?php

namespace Pushword\Newsletter\Service;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Pushword\Newsletter\Entity\Automation;
use Pushword\Newsletter\Entity\Enrollment;
use Pushword\Newsletter\Entity\TriggerLog;
use Pushword\Newsletter\Repository\AutomationRepository;
use Pushword\Newsletter\Repository\CampaignRepository;
use Pushword\Newsletter\Repository\EnrollmentRepository;
use Pushword\Newsletter\Repository\TriggerLogRepository;
use Pushword\Newsletter\Segment\SegmentException;
use Pushword\Newsletter\Segment\SegmentResolver;
use Pushword\Newsletter\Trigger\BroadcastScheduler;
use Pushword\Newsletter\Trigger\PlaceholderRenderer;
use Pushword\Newsletter\Trigger\TriggerSourceRegistry;
use Throwable;

/**
 * Runs every automation: asks each one's source what newly happened, starts the
 * sequence at it, and moves along the sequences already under way.
 *
 * Idempotent by construction. An occurrence is handled once because handling it
 * writes a {@see TriggerLog} row, and the source is asked to exclude what that
 * table already holds — so a tick that runs twice, or overlaps a previous one,
 * cannot start a second sequence for the same subject.
 */
final readonly class AutomationRunner
{
    public function __construct(
        private TriggerSourceRegistry $sources,
        private AutomationRepository $automationRepository,
        private TriggerLogRepository $logRepository,
        private EnrollmentRepository $enrollmentRepository,
        private CampaignRepository $campaignRepository,
        private SegmentResolver $segmentResolver,
        private BroadcastScheduler $broadcastScheduler,
        private PlaceholderRenderer $renderer,
        private EntityManagerInterface $entityManager,
        private NewsletterMailer $mailer,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Start every sequence that newly has something to start it.
     *
     * @return array{enrolled: int, scheduled: int}
     */
    public function trigger(DateTimeImmutable $now): array
    {
        $enrolled = 0;
        $scheduled = 0;

        foreach ($this->automationRepository->findEnabled() as $automation) {
            $report = $this->triggerOne($automation, $now);
            $enrolled += $report['enrolled'];
            $scheduled += $report['scheduled'];
        }

        return ['enrolled' => $enrolled, 'scheduled' => $scheduled];
    }

    /** @return array{enrolled: int, scheduled: int} */
    public function triggerOne(Automation $automation, DateTimeImmutable $now): array
    {
        $none = ['enrolled' => 0, 'scheduled' => 0];
        $source = $this->sources->for($automation);

        // An automation with no steps is being written, not switched on. Handling
        // its occurrences would mark them done and mail nothing, so the steps
        // added an hour later would have nothing left to react to.
        if (! $automation->enabled || null === $source || null === $automation->audience || 0 === $automation->countSteps()) {
            return $none;
        }

        try {
            $occurrences = $source->occurrences($automation, $now);
        } catch (SegmentException $segmentException) {
            // A rule nobody can fix from here — hand-edited, or left behind by a
            // grammar change. The automation stays quiet rather than failing the
            // whole tick, and says so once per run.
            $this->logger->error('Newsletter automation has an unreadable trigger rule.', [
                'automation' => $automation->id,
                'source' => $automation->source,
                'error' => $segmentException->getMessage(),
            ]);

            return $none;
        }

        $enrolled = 0;
        $scheduled = 0;

        foreach ($occurrences as $occurrence) {
            if (null !== $occurrence->contact) {
                $this->entityManager->persist(new Enrollment(
                    $occurrence->contact,
                    $automation,
                    $occurrence->occurredAt->modify('+'.$automation->delayToStep(0).' minutes'),
                    $occurrence->subjectId,
                    $occurrence->placeholders,
                ));
                ++$enrolled;
            } else {
                $scheduled += \count($this->broadcastScheduler->schedule($automation, $occurrence));
            }

            $this->entityManager->persist(new TriggerLog($automation, $occurrence->subjectId));
        }

        if ($enrolled > 0 || $scheduled > 0) {
            $this->entityManager->flush();
        }

        return ['enrolled' => $enrolled, 'scheduled' => $scheduled];
    }

    /**
     * Drop the campaigns whose subject stopped deserving them during the delay —
     * a page unpublished the evening it was announced.
     *
     * Only campaigns that have not been armed are considered: past that point the
     * recipients are frozen and some of the mails are already out.
     *
     * @return int the number of campaigns cancelled
     */
    public function cancelStale(): int
    {
        $cancelled = 0;

        foreach ($this->campaignRepository->findPendingTriggered() as $campaign) {
            $automation = $campaign->automation;
            $subjectId = $campaign->triggerSubjectId;
            if (null === $automation) {
                continue;
            }

            if (null === $subjectId) {
                continue;
            }

            if ($this->stillDeserved($automation, $subjectId)) {
                continue;
            }

            $this->entityManager->remove($campaign);
            ++$cancelled;

            // The marker goes with it, but only while nothing has gone out yet:
            // should the page be published again, that publication deserves the
            // mail this one was about to get. Once a step has been armed the
            // subject has been announced, and re-announcing it is not a fix.
            $log = $this->logRepository->findFor($automation, $subjectId);

            if (null !== $log && ! $this->campaignRepository->hasArmed($automation, $subjectId)) {
                $this->entityManager->remove($log);
            }
        }

        if ($cancelled > 0) {
            $this->entityManager->flush();
        }

        return $cancelled;
    }

    /**
     * An automation whose source is gone answers yes: a bundle being uninstalled
     * is not a reason to cancel the mails its automations already scheduled.
     */
    private function stillDeserved(Automation $automation, int $subjectId): bool
    {
        return $this->sources->for($automation)?->stillMatches($subjectId) ?? true;
    }

    /**
     * Send the drip steps that are due, up to `$budget` mails.
     *
     * @return int the number of mails handed to the transport
     */
    public function advance(int $budget): int
    {
        if ($budget < 1) {
            return 0;
        }

        $now = new DateTimeImmutable();
        $sent = 0;

        foreach ($this->enrollmentRepository->findDue($now, $budget) as $enrollment) {
            if ($this->send($enrollment, $now)) {
                ++$sent;
            }
        }

        $this->entityManager->flush();

        return $sent;
    }

    /** @return bool whether a mail was actually handed to the transport */
    private function send(Enrollment $enrollment, DateTimeImmutable $now): bool
    {
        $automation = $enrollment->automation;
        $contact = $enrollment->contact;

        // A disabled automation is paused, not cancelled: its enrollments wait.
        if (! $automation->enabled) {
            return false;
        }

        if (! $contact->isSubscribed()) {
            $enrollment->stop();

            return false;
        }

        $stopWhen = $automation->stopWhen;
        if ([] !== $stopWhen && $this->segmentResolver->matches($contact, $stopWhen)) {
            $enrollment->stop();

            return false;
        }

        $step = $automation->getStep($enrollment->position);
        if (null === $step) {
            $enrollment->finish();

            return false;
        }

        $delivered = true;
        $placeholders = $enrollment->placeholders;

        try {
            $this->mailer->sendStep(
                $step,
                $contact,
                $this->renderer->renderSubject($step->subject, $placeholders),
                $this->renderer->render($step->bodyMarkdown, $placeholders),
            );
        } catch (Throwable $throwable) {
            // The sequence moves on rather than retrying: a permanent failure
            // would otherwise block this contact's drip at the same step forever.
            // The loss is one step, and it is visible here.
            $this->logger->error('Newsletter step could not be sent.', [
                'automation' => $automation->id,
                'step' => $step->position,
                'contact' => $contact->id,
                'error' => $throwable->getMessage(),
            ]);
            $delivered = false;
        }

        $next = $automation->getStep($enrollment->position + 1);
        $enrollment->advance($now->modify('+'.($next->delayMinutes ?? 0).' minutes'));

        return $delivered;
    }
}
