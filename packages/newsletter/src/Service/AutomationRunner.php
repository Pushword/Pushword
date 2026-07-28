<?php

namespace Pushword\Newsletter\Service;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Pushword\Newsletter\Entity\Automation;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Entity\Enrollment;
use Pushword\Newsletter\Repository\EnrollmentRepository;
use Pushword\Newsletter\Segment\SegmentResolver;
use Throwable;

/**
 * Runs the drip: enrolls whoever newly matches an automation, then sends the
 * step each active enrollment is due for.
 *
 * Enrollment is idempotent by construction — the unique (contact, automation)
 * constraint plus an explicit "not already enrolled" filter — so a tick that
 * runs twice, or overlaps a previous one, cannot start a second sequence.
 */
final readonly class AutomationRunner
{
    public function __construct(
        private SegmentResolver $segmentResolver,
        private EnrollmentRepository $enrollmentRepository,
        private EntityManagerInterface $entityManager,
        private NewsletterMailer $mailer,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Enroll every subscribed contact matching the automation that is not
     * enrolled yet and registered on or after its `enrollFrom`.
     *
     * @return int the number of new enrollments
     */
    public function enroll(Automation $automation): int
    {
        $audience = $automation->getAudience();

        if (! $automation->isEnabled() || null === $audience || 0 === $automation->countSteps()) {
            return 0;
        }

        $firstStep = $automation->getStep(0);
        if (null === $firstStep) {
            return 0;
        }

        $alreadyEnrolled = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(enrolled.contact)')
            ->from(Enrollment::class, 'enrolled')
            ->andWhere('enrolled.automation = :automation')
            ->getDQL();

        $queryBuilder = $this->segmentResolver->queryBuilder($audience, $automation->getEnrollWhen())
            ->andWhere('c.createdAt >= :enrollFrom')
            ->andWhere('c.id NOT IN ('.$alreadyEnrolled.')')
            ->setParameter('enrollFrom', $automation->getEnrollFrom())
            ->setParameter('automation', $automation);

        $now = new DateTimeImmutable();
        $count = 0;

        /** @var Contact $contact */
        foreach ($queryBuilder->getQuery()->getResult() as $contact) {
            $this->entityManager->persist(
                new Enrollment($contact, $automation, $now->modify('+'.$firstStep->getDelayMinutes().' minutes'))
            );
            ++$count;
        }

        if ($count > 0) {
            $this->entityManager->flush();
        }

        return $count;
    }

    /**
     * Send the steps that are due, up to `$budget` mails.
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
        $automation = $enrollment->getAutomation();
        $contact = $enrollment->getContact();

        // A disabled automation is paused, not cancelled: its enrollments wait.
        if (! $automation->isEnabled()) {
            return false;
        }

        if (! $contact->isSubscribed()) {
            $enrollment->stop();

            return false;
        }

        $stopWhen = $automation->getStopWhen();
        if ([] !== $stopWhen && $this->segmentResolver->matches($contact, $stopWhen)) {
            $enrollment->stop();

            return false;
        }

        $step = $automation->getStep($enrollment->getPosition());
        if (null === $step) {
            $enrollment->finish();

            return false;
        }

        $delivered = true;

        try {
            $this->mailer->sendStep($step, $contact);
        } catch (Throwable $throwable) {
            // The sequence moves on rather than retrying: a permanent failure
            // would otherwise block this contact's drip at the same step forever.
            // The loss is one step, and it is visible here.
            $this->logger->error('Newsletter step could not be sent.', [
                'automation' => $automation->id,
                'step' => $step->getPosition(),
                'contact' => $contact->id,
                'error' => $throwable->getMessage(),
            ]);
            $delivered = false;
        }

        $next = $automation->getStep($enrollment->getPosition() + 1);
        $enrollment->advance($now->modify('+'.($next?->getDelayMinutes() ?? 0).' minutes'));

        return $delivered;
    }
}
