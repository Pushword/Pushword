<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Tests\Service;

use ArrayIterator;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Newsletter\Delivery\SendContext;
use Pushword\Newsletter\Entity\Automation;
use Pushword\Newsletter\Entity\AutomationDelivery;
use Pushword\Newsletter\Entity\Enrollment;
use Pushword\Newsletter\Enum\EnrollmentStatus;
use Pushword\Newsletter\Enum\RecipientState;
use Pushword\Newsletter\Event\AutomationDeliveryResult;
use Pushword\Newsletter\Event\PrepareAutomationDelivery;
use Pushword\Newsletter\Repository\AutomationRepository;
use Pushword\Newsletter\Repository\CampaignRepository;
use Pushword\Newsletter\Repository\EnrollmentRepository;
use Pushword\Newsletter\Repository\TriggerLogRepository;
use Pushword\Newsletter\Segment\SegmentResolver;
use Pushword\Newsletter\Service\AutomationRunner;
use Pushword\Newsletter\Service\NewsletterMailer;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;
use Pushword\Newsletter\Trigger\BroadcastScheduler;
use Pushword\Newsletter\Trigger\PlaceholderRenderer;
use Pushword\Newsletter\Trigger\TriggerOccurrence;
use Pushword\Newsletter\Trigger\TriggerSource;
use Pushword\Newsletter\Trigger\TriggerSourceRegistry;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Email;

#[Group('integration')]
#[AllowMockObjectsWithoutExpectations]
final class AutomationDeliveryTest extends AbstractNewsletterTestCase
{
    use MailerAssertionsTrait;

    public function testTheSourceIsRevalidatedBeforeEveryStep(): void
    {
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'reader@example.tld');
        $automation = $this->createAutomation($audience, [
            ['delay' => 0, 'subject' => 'First'], ['delay' => 0, 'subject' => 'Second'],
        ]);
        $occurrence = new TriggerOccurrence(123, new DateTimeImmutable('-1 minute'), contact: $contact);
        $source = $this->createMock(TriggerSource::class);
        $source->method('name')->willReturn('contact');
        $source->method('occurrences')->willReturn([$occurrence]);
        $source->expects(self::exactly(2))->method('stillMatches')->with(123)->willReturn(true, false);
        $runner = $this->runner($source);
        $runner->triggerOne($automation, new DateTimeImmutable());
        self::assertSame(1, $runner->advance(10));
        self::assertSame(0, $runner->advance(10));
        self::assertEmailCount(1);
        $enrollment = $this->enrollment($automation);
        self::assertSame(EnrollmentStatus::Stopped, $enrollment->status);
        self::assertSame('subject_no_longer_matches', $enrollment->stopReason);
        self::assertCount(1, $this->deliveries($automation));
    }

    public function testAMissingSourceStopsDelivery(): void
    {
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'reader@example.tld');
        $automation = $this->createAutomation($audience, [['delay' => 0, 'subject' => 'First']]);
        $enrollment = new Enrollment($contact, $automation, new DateTimeImmutable('-1 minute'), 123);
        $this->entityManager->persist($enrollment);
        $this->entityManager->flush();
        self::assertSame(0, $this->runner()->advance(10));
        self::assertSame('source_unavailable', $enrollment->stopReason);
        self::assertEmailCount(0);
    }

    public function testAVetoStopsBeforeLowerPriorityPreparation(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'reader@example.tld');
        $automation = $this->createAutomation($audience, [['delay' => 0, 'subject' => 'First']]);
        $runner = self::getContainer()->get(AutomationRunner::class);
        $runner->triggerOne($automation, new DateTimeImmutable());

        $dispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        $dispatcher->addListener(PrepareAutomationDelivery::class, static function (PrepareAutomationDelivery $event) use ($automation): void {
            self::assertSame($automation, $event->automation);
            self::assertSame($event->enrollment->contact, $event->contact);
            self::assertSame($event->enrollment->subjectId, $event->subjectId);
            self::assertSame(0, $event->step->position);
            $event->veto('application_disabled');
        }, 10);
        $dispatcher->addListener(PrepareAutomationDelivery::class, static function (): never {
            self::fail('A veto must prevent reserving or issuing credentials.');
        });
        $dispatcher->addListener(AutomationDeliveryResult::class, static function (): never {
            self::fail('A veto is not a transport attempt.');
        });
        self::assertFalse($runner->advanceOne($this->enrollment($automation)));
        self::assertSame('application_disabled', $this->enrollment($automation)->stopReason);
        self::assertSame([], $this->deliveries($automation));
        self::assertEmailCount(0);
    }

    /** @return iterable<string, array{string, string}> */
    public static function locales(): iterable
    {
        yield 'French' => ['fr', 'Se désinscrire'];
        yield 'English' => ['en', 'Unsubscribe'];
        yield 'US English' => ['en-US', 'Unsubscribe'];
        yield 'German' => ['de', 'Abmelden'];
        yield 'Dutch' => ['nl', 'Uitschrijven'];
        yield 'Italian' => ['it', 'Annulla iscrizione'];
    }

    #[DataProvider('locales')]
    public function testOccurrenceIdentityAndLateLinksSurvivePersistence(string $locale, string $unsubscribeLabel): void
    {
        $audience = $this->createAudience();
        $audience->clickTracking = true;
        $audience->utmSource = 'shared-audience';
        $audience->postalAddress = 'Original brand';

        $contact = $this->createContact($audience, 'reader@example.tld', locale: 'fr');
        $contact->clickTrackingConsentAt = new DateTimeImmutable();

        $automation = $this->createAutomation($audience, [['delay' => 0, 'subject' => 'Visit {{ subject.name }} {{ subject.resumeUrl }}']]);
        $automation->getOrderedSteps()[0]->bodyMarkdown = '[Resume]({{ subject.resumeUrl }}) [Article](/article)';
        $this->entityManager->flush();
        $context = new SendContext('admin-block-editor.test', 'newsletter@example.de', 'Other brand', 'help@example.de', 'Other postal address', '[Legal](https://admin-block-editor.test/legal)', 'Other preferences');
        $occurrence = new TriggerOccurrence(123, new DateTimeImmutable('-1 minute'), ['subject.name' => 'A trip'], $contact, locale: $locale, sendContext: $context);
        $source = self::createStub(TriggerSource::class);
        $source->method('name')->willReturn('contact');
        $source->method('occurrences')->willReturn([$occurrence]);
        $source->method('stillMatches')->willReturn(true);
        $runner = $this->runner($source);
        $runner->triggerOne($automation, new DateTimeImmutable());
        // Force a DB round trip; a tick can be days after enrollment.
        $this->entityManager->clear();
        $enrollment = $this->enrollment($automation);
        self::assertSame($context->toArray(), $enrollment->getSendContext()?->toArray());
        self::assertSame($locale, $enrollment->locale);
        $url = 'https://admin-block-editor.test/auth/secret?x=1&y=2';
        $dispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        $dispatcher->addListener(PrepareAutomationDelivery::class, static function (PrepareAutomationDelivery $event) use ($url): void {
            $event->placeholders['subject.resumeUrl'] = $url;
            $event->placeholders['subject.name'] = 'Late body name';
            $event->untrackedUrls[] = $url;
        });
        $results = [];
        $dispatcher->addListener(AutomationDeliveryResult::class, static function (AutomationDeliveryResult $event) use (&$results): void {
            $results[] = $event->delivery->state;
            self::assertSame(0, $event->enrollment->position);
        });
        self::assertSame(1, $runner->advance(10));
        self::assertSame([RecipientState::Sent], $results);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('Visit A trip {{ subject.resumeUrl }}', $email->getSubject());
        self::assertSame('newsletter@example.de', $email->getFrom()[0]->getAddress());
        self::assertSame('Other brand', $email->getFrom()[0]->getName());
        self::assertSame('help@example.de', $email->getReplyTo()[0]->getAddress());
        $html = (string) $email->getHtmlBody();
        self::assertStringContainsString('<html lang="'.$locale.'">', $html);
        self::assertStringContainsString($unsubscribeLabel, $html);
        self::assertStringContainsString('Other preferences', $html);
        self::assertStringContainsString('Other postal address', $html);
        self::assertStringContainsString('href="https://admin-block-editor.test/legal"', $html);
        self::assertStringContainsString('href="'.htmlspecialchars($url, \ENT_QUOTES).'"', $html);
        self::assertStringContainsString('https://admin-block-editor.test/newsletter/c/', $html);
        self::assertStringContainsString($url, (string) $email->getTextBody());
        self::assertStringContainsString('Other postal address', (string) $email->getTextBody());
        self::assertStringContainsString('https://admin-block-editor.test/newsletter/unsubscribe/'.$enrollment->contact->token, $email->getHeaders()->toString());
        self::assertStringContainsString('List-Unsubscribe-Post: List-Unsubscribe=One-Click', $email->getHeaders()->toString());
        self::assertSame('fr', $enrollment->contact->locale);
        self::assertSame($audience->id, $enrollment->contact->audience->id);
        self::assertSame('newsletter@localhost.dev', $enrollment->contact->audience->fromEmail);
        self::assertStringNotContainsString('secret', json_encode($enrollment->placeholders, \JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('secret', $this->deliveries($automation)[0]->subject);
        $row = $this->entityManager->getConnection()->fetchAssociative('SELECT * FROM newsletter_enrollment WHERE id = ?', [$enrollment->id]);
        self::assertStringNotContainsString('secret', json_encode($row, \JSON_THROW_ON_ERROR));
        // The occurrence host must still unsubscribe the original consent row.
        $this->client->request('POST', 'https://admin-block-editor.test/newsletter/unsubscribe/'.$enrollment->contact->token);
        $this->entityManager->refresh($enrollment->contact);
        self::assertFalse($enrollment->contact->isSubscribed());
    }

    public function testAFailedPreparationCannotLeakOrRetryAClaimedSubject(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'reader@example.tld');
        $automation = $this->createAutomation($audience, [['delay' => 0, 'subject' => 'First']]);
        $otherAutomation = $this->createAutomation($audience, [['delay' => 0, 'subject' => 'Other']]);
        $runner = self::getContainer()->get(AutomationRunner::class);
        $runner->triggerOne($automation, new DateTimeImmutable());
        $runner->triggerOne($otherAutomation, new DateTimeImmutable());

        $claimed = false;
        $dispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        $dispatcher->addListener(PrepareAutomationDelivery::class, static function (PrepareAutomationDelivery $event) use (&$claimed): void {
            if ($claimed) {
                $event->veto('subject_already_reserved');

                return;
            }

            $claimed = true;

            throw new RuntimeException('https://example.test/auth/secret');
        });
        $results = [];
        $dispatcher->addListener(AutomationDeliveryResult::class, static function (AutomationDeliveryResult $event) use (&$results): void {
            $results[] = $event->delivery->state;
        });
        self::assertSame(0, $runner->advance(10));
        self::assertSame(0, $runner->advance(10));
        self::assertEmailCount(0);
        self::assertSame([RecipientState::Failed], $results);
        self::assertSame(RuntimeException::class, $this->deliveries($automation)[0]->error);
        self::assertSame('subject_already_reserved', $this->enrollment($otherAutomation)->stopReason);
        self::assertSame([], $this->deliveries($otherAutomation));
    }

    public function testAResultListenerFailureDoesNotResubmitAnAcceptedMail(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'reader@example.tld');
        $automation = $this->createAutomation($audience, [['delay' => 0, 'subject' => 'First']]);
        $runner = self::getContainer()->get(AutomationRunner::class);
        $runner->triggerOne($automation, new DateTimeImmutable());
        self::getContainer()->get(EventDispatcherInterface::class)->addListener(AutomationDeliveryResult::class, static function (): never {
            throw new RuntimeException('secret');
        });
        self::assertSame(1, $runner->advance(10));
        self::assertSame(0, $runner->advance(10));
        self::assertEmailCount(1);
        self::assertSame('result_notification_failed', $this->enrollment($automation)->stopReason);
        self::assertSame(RecipientState::Sent, $this->deliveries($automation)[0]->state);
    }

    public function testDefaultContextAndLocaleAreFrozenAtEnrollment(): void
    {
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'reader@example.tld', locale: 'en');
        $automation = $this->createAutomation($audience, [['delay' => 0, 'subject' => 'First']]);
        $runner = self::getContainer()->get(AutomationRunner::class);
        $runner->triggerOne($automation, new DateTimeImmutable());

        $contact->locale = 'de';
        $audience->fromEmail = 'changed@example.de';
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame(1, $runner->advance(10));
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('newsletter@localhost.dev', $email->getFrom()[0]->getAddress());
        self::assertStringContainsString('<html lang="en">', (string) $email->getHtmlBody());
        self::assertSame('de', $this->enrollment($automation)->contact->locale);
    }

    public function testAnAcceptedClaimVetoesAnotherAutomationWithoutIssuingAnotherLink(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'reader@example.tld');
        $automation = $this->createAutomation($audience, [['delay' => 0, 'subject' => 'First']]);
        $other = $this->createAutomation($audience, [['delay' => 0, 'subject' => 'Other']]);
        $runner = self::getContainer()->get(AutomationRunner::class);
        $runner->triggerOne($automation, new DateTimeImmutable());
        $runner->triggerOne($other, new DateTimeImmutable());

        $claims = 0;
        self::getContainer()->get(EventDispatcherInterface::class)->addListener(
            PrepareAutomationDelivery::class,
            static function (PrepareAutomationDelivery $event) use (&$claims): void {
                if ($claims > 0) {
                    $event->veto('subject_already_reserved');

                    return;
                }

                ++$claims;
                $event->placeholders['subject.resumeUrl'] = 'https://example.test/auth/secret';
            }
        );
        self::assertSame(1, $runner->advance(10));
        self::assertSame(0, $runner->advance(10));
        self::assertSame(1, $claims);
        self::assertEmailCount(1);
        self::assertSame('subject_already_reserved', $this->enrollment($other)->stopReason);
    }

    public function testATransportVetoReportsFailureAfterPreparation(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'reader@example.tld');
        $automation = $this->createAutomation($audience, [['delay' => 0, 'subject' => 'First']]);
        $runner = self::getContainer()->get(AutomationRunner::class);
        $runner->triggerOne($automation, new DateTimeImmutable());

        $dispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        $prepared = false;
        $dispatcher->addListener(PrepareAutomationDelivery::class, static function (PrepareAutomationDelivery $event) use (&$prepared): void {
            $prepared = true;
            $event->placeholders['subject.resumeUrl'] = 'https://example.test/auth/secret';
        });
        $dispatcher->addListener(
            MessageEvent::class,
            static function (MessageEvent $event): void {
                $event->reject();
            }
        );
        $results = [];
        $dispatcher->addListener(AutomationDeliveryResult::class, static function (AutomationDeliveryResult $event) use (&$results): void {
            $results[] = $event->delivery->state;
        });
        self::assertSame(0, $runner->advance(10));
        self::assertTrue($prepared);
        self::assertSame([RecipientState::Failed], $results);
        self::assertSame(LogicException::class, $this->deliveries($automation)[0]->error);
        self::assertSame(0, $runner->advance(10));
    }

    private function runner(?TriggerSource $source = null): AutomationRunner
    {
        $container = self::getContainer();

        return new AutomationRunner(
            new TriggerSourceRegistry(new ArrayIterator(null !== $source ? [$source] : [])),
            $container->get(AutomationRepository::class),
            $container->get(TriggerLogRepository::class),
            $container->get(EnrollmentRepository::class),
            $container->get(CampaignRepository::class),
            $container->get(SegmentResolver::class),
            $container->get(BroadcastScheduler::class),
            $container->get(PlaceholderRenderer::class),
            $this->entityManager,
            $container->get(NewsletterMailer::class),
            $container->get('logger'),
            $container->get(EventDispatcherInterface::class),
        );
    }

    private function enrollment(Automation $automation): Enrollment
    {
        $enrollment = $this->entityManager->getRepository(Enrollment::class)->findOneBy(['automation' => $automation]);
        self::assertInstanceOf(Enrollment::class, $enrollment);

        return $enrollment;
    }

    /** @return list<AutomationDelivery> */
    private function deliveries(Automation $automation): array
    {
        return $this->entityManager->getRepository(AutomationDelivery::class)->findBy(['automation' => $automation], ['id' => 'ASC']);
    }
}
