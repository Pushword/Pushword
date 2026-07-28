<?php

namespace Pushword\Newsletter\Tests\Service;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Newsletter\Entity\Automation;
use Pushword\Newsletter\Entity\Enrollment;
use Pushword\Newsletter\Enum\EnrollmentStatus;
use Pushword\Newsletter\Service\AutomationRunner;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\Mime\Email;

#[Group('integration')]
final class AutomationRunnerTest extends AbstractNewsletterTestCase
{
    use MailerAssertionsTrait;

    /** @var list<array{delay: int, subject: string}> */
    private const array TWO_STEPS = [
        ['delay' => 0, 'subject' => 'Welcome'],
        ['delay' => 4320, 'subject' => 'Three days later'],
    ];

    private function runner(): AutomationRunner
    {
        return self::getContainer()->get(AutomationRunner::class);
    }

    public function testEnrollmentIsIdempotent(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'new@example.tld');
        $automation = $this->createAutomation($audience, self::TWO_STEPS);

        self::assertSame(1, $this->runner()->enroll($automation));
        self::assertSame(0, $this->runner()->enroll($automation), 'a second tick must not start a second sequence');
        self::assertCount(1, $this->enrollments($automation));
    }

    public function testOnlySubscribedContactsAreEnrolled(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'in@example.tld');
        $this->createContact($audience, 'pending@example.tld', subscribed: false);

        self::assertSame(1, $this->runner()->enroll($this->createAutomation($audience, self::TWO_STEPS)));
    }

    public function testEnrollWhenNarrowsTheEnrollment(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'trek@example.tld', ['AmTrek']);
        $this->createContact($audience, 'other@example.tld');

        $automation = $this->createAutomation($audience, self::TWO_STEPS, [
            ['field' => 'tag', 'op' => 'has', 'value' => 'AmTrek'],
        ]);

        self::assertSame(1, $this->runner()->enroll($automation));
        self::assertSame('trek@example.tld', $this->enrollments($automation)[0]->getContact()->getEmail());
    }

    /** The guard against mailing an entire existing base the day an automation is switched on. */
    public function testContactsRegisteredBeforeEnrollFromAreNeverEnrolled(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'old@example.tld', registeredAt: new DateTimeImmutable('-30 days'));
        $this->createContact($audience, 'fresh@example.tld');

        $automation = $this->createAutomation($audience, self::TWO_STEPS);
        $automation->setEnrollFrom(new DateTimeImmutable('-1 day'));

        $this->entityManager->flush();

        self::assertSame(1, $this->runner()->enroll($automation));
        self::assertSame('fresh@example.tld', $this->enrollments($automation)[0]->getContact()->getEmail());
    }

    public function testADisabledAutomationEnrollsNobody(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'new@example.tld');
        $automation = $this->createAutomation($audience, self::TWO_STEPS);
        $automation->setEnabled(false);

        $this->entityManager->flush();

        self::assertSame(0, $this->runner()->enroll($automation));
    }

    public function testAnAutomationWithoutStepsEnrollsNobody(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'new@example.tld');

        self::assertSame(0, $this->runner()->enroll($this->createAutomation($audience, [])));
    }

    public function testTheFirstStepGoesOutAndTheSecondIsScheduled(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'new@example.tld');
        $automation = $this->createAutomation($audience, self::TWO_STEPS);
        $this->runner()->enroll($automation);

        self::assertSame(1, $this->runner()->advance(10));

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('Welcome', $email->getSubject());

        $enrollment = $this->enrollments($automation)[0];
        self::assertSame(1, $enrollment->getPosition());
        self::assertSame(EnrollmentStatus::Active, $enrollment->getStatus());
        self::assertGreaterThan(new DateTimeImmutable('+2 days'), $enrollment->getNextRunAt());
    }

    public function testTheSecondRunSendsNothingUntilTheDelayHasElapsed(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'new@example.tld');
        $automation = $this->createAutomation($audience, self::TWO_STEPS);
        $this->runner()->enroll($automation);
        $this->runner()->advance(10);

        self::assertSame(0, $this->runner()->advance(10));
    }

    public function testTheSequenceEndsAfterTheLastStep(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'new@example.tld');
        $automation = $this->createAutomation($audience, self::TWO_STEPS);
        $this->runner()->enroll($automation);
        $this->runner()->advance(10);

        $this->makeDue($automation);

        self::assertSame(1, $this->runner()->advance(10));

        self::assertSame('Three days later', $this->lastMailSubject());
        self::assertSame(EnrollmentStatus::Done, $this->enrollments($automation)[0]->getStatus());
    }

    public function testAStopConditionEndsTheSequence(): void
    {
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'buyer@example.tld');
        $automation = $this->createAutomation($audience, self::TWO_STEPS, stopWhen: [
            ['field' => 'prop.lastBoughtProduct', 'op' => 'isSet'],
        ]);
        $this->runner()->enroll($automation);

        $contact->setCustomProperty('lastBoughtProduct', 'tmb');
        $this->entityManager->flush();

        self::assertSame(0, $this->runner()->advance(10));
        self::assertEmailCount(0);
        self::assertSame(EnrollmentStatus::Stopped, $this->enrollments($automation)[0]->getStatus());
    }

    public function testUnsubscribingEndsTheSequence(): void
    {
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'leaving@example.tld');
        $automation = $this->createAutomation($audience, self::TWO_STEPS);
        $this->runner()->enroll($automation);

        $contact->unsubscribe();
        $this->entityManager->flush();

        self::assertSame(0, $this->runner()->advance(10));
        self::assertEmailCount(0);
        self::assertSame(EnrollmentStatus::Stopped, $this->enrollments($automation)[0]->getStatus());
    }

    /** Disabling pauses: the enrollment keeps its place instead of being cancelled. */
    public function testADisabledAutomationHoldsItsEnrollments(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'new@example.tld');
        $automation = $this->createAutomation($audience, self::TWO_STEPS);
        $this->runner()->enroll($automation);

        $automation->setEnabled(false);
        $this->entityManager->flush();

        self::assertSame(0, $this->runner()->advance(10));
        self::assertEmailCount(0);

        $enrollment = $this->enrollments($automation)[0];
        self::assertSame(EnrollmentStatus::Active, $enrollment->getStatus());
        self::assertSame(0, $enrollment->getPosition());
    }

    public function testEnrollmentIsScopedToTheAudience(): void
    {
        $audience = $this->createAudience();
        $other = $this->createAudience();
        $this->createContact($audience, 'mine@example.tld');
        $this->createContact($other, 'theirs@example.tld');

        self::assertSame(1, $this->runner()->enroll($this->createAutomation($audience, self::TWO_STEPS)));
    }

    /** Messages accumulate across service calls in one test; the drip's latest is the last one. */
    private function lastMailSubject(): string
    {
        $messages = self::getMailerMessages();
        $last = end($messages);
        self::assertInstanceOf(Email::class, $last);

        return (string) $last->getSubject();
    }

    /** @return list<Enrollment> */
    private function enrollments(Automation $automation): array
    {
        /** @var list<Enrollment> $enrollments */
        $enrollments = $this->entityManager->getRepository(Enrollment::class)
            ->findBy(['automation' => $automation], ['id' => 'ASC']);

        return $enrollments;
    }

    /** Bring every active enrollment of an automation forward without waiting for the delay. */
    private function makeDue(Automation $automation): void
    {
        foreach ($this->enrollments($automation) as $enrollment) {
            $this->entityManager->detach($enrollment);
        }

        $this->entityManager->getConnection()->executeStatement(
            'UPDATE newsletter_enrollment SET next_run_at = :when WHERE automation_id = :automation',
            ['when' => date('Y-m-d H:i:s', time() - 60), 'automation' => $automation->id],
        );
    }

    protected function tearDown(): void
    {
        // Contacts are purged by the base class; make sure nothing stays attached.
        $this->entityManager->clear();
        parent::tearDown();
    }
}
