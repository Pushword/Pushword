<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Tests\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Newsletter\Delivery\SendContext;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Entity\DeliveryMessage;
use Pushword\Newsletter\Service\BounceSignature;
use Pushword\Newsletter\Service\LinkGenerator;
use Pushword\Newsletter\Service\MailRenderer;
use Pushword\Newsletter\Service\NewsletterMailer;
use Pushword\Newsletter\Service\SenderProfileRegistry;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\EventListener\EnvelopeListener;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Group('integration')]
final class NewsletterMailerTest extends AbstractNewsletterTestCase
{
    use MailerAssertionsTrait;

    public function testFirstNameResolvesInTranslationsTestsAndAutomationWithoutChangingConsent(): void
    {
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'reader@example.tld', customProperties: ['firstName' => 'Élodie'], locale: 'fr');
        $campaign = $this->createCampaign($audience);
        $campaign->translations = ['fr' => [
            'subject' => 'Bonjour %firstName|à vous%',
            'preheader' => 'Pour %firstName|vous%',
            'bodyMarkdown' => 'Bonjour **%firstName|à vous%**.',
        ]];
        $automation = $this->createAutomation($audience, [['delay' => 0, 'subject' => 'Hello']]);
        $mailer = self::getContainer()->get(NewsletterMailer::class);
        $mailer->sendCampaign($campaign, $contact);
        $mailer->sendTest($audience, 'Hello %firstName|there%', 'Hello **%firstName|there%**.', null, 'test@example.tld', null);
        $mailer->sendStep($automation->getOrderedSteps()[0], $contact, 'Hello %firstName|there%', 'Hello **%firstName|there%**.');
        self::assertEmailCount(3);
        foreach (['Bonjour Élodie', '[TEST] Hello Test', 'Hello Élodie'] as $index => $subject) {
            $email = self::getMailerMessage($index);
            self::assertInstanceOf(Email::class, $email);
            self::assertSame($subject, $email->getSubject());
            $name = 1 === $index ? 'Test' : 'Élodie';
            self::assertEmailHtmlBodyContains($email, '<strong>'.$name.'</strong>');
            self::assertEmailTextBodyContains($email, '**'.$name.'**');
            if (0 === $index) {
                self::assertEmailHtmlBodyContains($email, 'Pour Élodie');
            }

            if (1 !== $index) {
                self::assertTrue($email->getHeaders()->has('List-Unsubscribe'));
                self::assertTrue($email->getHeaders()->has('List-Unsubscribe-Post'));
            }
        }
    }

    /** @return iterable<string, array{string, ?string, bool, bool}> */
    public static function campaignDesigns(): iterable
    {
        yield 'inherit the audience frame' => ['framed', null, true, true];
        yield 'frame this campaign only' => ['simple', 'framed', true, true];
        yield 'replace the audience preset with editable identity' => ['twig:pushword', 'simple', true, false];
        yield 'use a fixed campaign preset' => ['simple', 'twig:pushword', false, true];
        yield 'use an unframed letter preset' => ['framed', 'twig:pushword_letter', false, false];
        yield 'use a bulletin preset' => ['simple', 'twig:pushword_bulletin', false, true];
    }

    #[DataProvider('campaignDesigns')]
    public function testCampaignAndTestEmailsUseTheChosenDesignWithoutChangingTheAudience(string $audienceLayout, ?string $campaignLayout, bool $manualIdentity, bool $framed): void
    {
        $audience = $this->createAudience();
        $audience->emailLayout = $audienceLayout;
        $audience->headerMarkdown = 'Shared brand heading';
        $audience->footerMarkdown = 'Shared brand signature';
        $audience->postalAddress = '12 Baker Street';

        $contact = $this->createContact($audience, 'reader@example.tld');
        $campaign = $this->createCampaign($audience);
        $campaign->emailLayout = $campaignLayout;

        $mailer = self::getContainer()->get(NewsletterMailer::class);
        $mailer->sendCampaign($campaign, $contact);
        $mailer->sendTest($audience, 'Test', 'Preview content', null, 'test@example.tld', null, emailLayout: $campaign->emailLayout);
        self::assertEmailCount(2);
        foreach ([0, 1] as $index) {
            $message = self::getMailerMessage($index);
            self::assertInstanceOf(Email::class, $message);
            $html = $message->getHtmlBody() ?? '';
            $text = $message->getTextBody() ?? '';
            self::assertIsString($html);
            self::assertIsString($text);
            self::assertSame($manualIdentity, str_contains($html, 'Shared brand heading'));
            self::assertSame($manualIdentity, str_contains($text, 'Shared brand signature'));
            self::assertSame($framed, str_contains($html, 'background-color:#f3f4f6'));
            self::assertStringContainsString('12 Baker Street', $html.$text);
            self::assertStringContainsString('Unsubscribe', $html);
        }

        self::assertSame($audienceLayout, $audience->emailLayout);
        self::assertSame('Shared brand heading', $audience->headerMarkdown);
        self::assertSame('Shared brand signature', $audience->footerMarkdown);
    }

    /** @return iterable<string, array{string, ?string, bool}> */
    public static function personalDesigns(): iterable
    {
        yield 'audience' => ['personal', null, true];
        yield 'campaign override' => ['framed', 'personal', true];
        yield 'campaign restores newsletter' => ['personal', 'simple', false];
    }

    #[DataProvider('personalDesigns')]
    public function testPersonalCampaignsAndTestsKeepConsentAlongsideContentAndSignature(string $audienceLayout, ?string $campaignLayout, bool $personal): void
    {
        $audience = $this->createAudience();
        $audience->emailLayout = $audienceLayout;
        $audience->headerMarkdown = 'Newsletter header';
        $audience->footerMarkdown = 'Newsletter copyright';
        $audience->postalAddress = '12 Baker Street';
        $audience->signatureMarkdown = '**Robin** at %siteName%';
        $audience->replyTo = 'robin@example.tld';

        $contact = $this->createContact($audience, 'reader@example.tld');
        $campaign = $this->createCampaign($audience);
        $campaign->emailLayout = $campaignLayout;
        $campaign->bodyMarkdown = 'Hello **%name%**.';
        $campaign->preheader = 'Newsletter preview text';
        self::assertFalse($campaign->isTransactional());

        $mailer = self::getContainer()->get(NewsletterMailer::class);
        $mailer->sendCampaign($campaign, $contact);
        $mailer->sendTest($audience, 'Test', $campaign->bodyMarkdown, $campaign->preheader, 'test@example.tld', null, emailLayout: $campaignLayout);
        self::assertEmailCount(2);
        foreach ([0, 1] as $index) {
            $email = self::getMailerMessage($index);
            self::assertInstanceOf(Email::class, $email);
            $html = $email->getHtmlBody() ?? '';
            $text = $email->getTextBody() ?? '';
            self::assertIsString($html);
            self::assertIsString($text);
            self::assertSame(! $personal, str_contains($html, '<table'));
            foreach (['Newsletter header', 'Newsletter copyright', 'Newsletter preview text'] as $decoration) {
                self::assertSame(! $personal, str_contains($html, $decoration), $decoration);
            }

            self::assertStringContainsString('12 Baker Street', $text);
            self::assertStringContainsString('12 Baker Street', $html);
            self::assertStringContainsString('Unsubscribe', $html);
            self::assertSame($personal, str_contains($html, '<strong>Robin</strong> at Pushword'));
            self::assertSame($personal, str_contains($text, '**Robin** at Pushword'));
            self::assertSame('robin@example.tld', $email->getReplyTo()[0]->getAddress());
            self::assertCount(1, $email->getTo());
            self::assertEmailHtmlBodyContains($email, 'Hello <strong>Test</strong>.');
            if (0 === $index) {
                $message = $this->entityManager->getRepository(DeliveryMessage::class)->findOneBy(['campaign' => $campaign]);
                self::assertNotNull($message);
                self::assertSame('<'.self::getContainer()->get(LinkGenerator::class)->unsubscribeUrl($contact, messageReference: $message->reference).'>', $email->getHeaders()->get('List-Unsubscribe')?->getBodyAsString());
                self::assertSame('List-Unsubscribe=One-Click', $email->getHeaders()->get('List-Unsubscribe-Post')?->getBodyAsString());
            }
        }

        self::assertSame($audienceLayout, $audience->emailLayout);
    }

    public function testPersonalAutomationAndCampaignWithoutAudienceKeepUnsubscribeHeaders(): void
    {
        $audience = $this->createAudience();
        $audience->emailLayout = 'personal';
        $audience->signatureMarkdown = 'Robin';

        $contact = $this->createContact($audience, 'reader@example.tld');
        $automation = $this->createAutomation($audience, [['delay' => 0, 'subject' => 'Welcome']]);
        self::assertFalse($automation->isTransactional());
        $campaign = new Campaign();
        $campaign->subject = 'Hello';
        $campaign->bodyMarkdown = 'Body';

        $this->entityManager->persist($campaign);

        $mailer = self::getContainer()->get(NewsletterMailer::class);
        $mailer->sendCampaign($campaign, $contact);
        $mailer->sendStep($automation->getOrderedSteps()[0], $contact, 'Welcome', 'Body', SendContext::fromAudience($audience));
        self::assertEmailCount(2);
        foreach ([0, 1] as $index) {
            $email = self::getMailerMessage($index);
            self::assertInstanceOf(Email::class, $email);
            self::assertTrue($email->getHeaders()->has('List-Unsubscribe'));
            self::assertTrue($email->getHeaders()->has('List-Unsubscribe-Post'));
            self::assertEmailTextBodyContains($email, "Body\n\nRobin");
            self::assertEmailHtmlBodyNotContains($email, '©');
        }
    }

    public function testCampaignsAndAutomationStepsUseTheAudienceDesign(): void
    {
        $audience = $this->createAudience();
        $audience->emailWrapped = true;
        $audience->headerMarkdown = 'Brand heading';
        $audience->footerMarkdown = 'Brand signature';

        $contact = $this->createContact($audience, 'reader@example.tld');
        $campaign = $this->createCampaign($audience);
        $automation = $this->createAutomation($audience, [['delay' => 0, 'subject' => 'Welcome']]);
        $mailer = self::getContainer()->get(NewsletterMailer::class);
        $mailer->sendCampaign($campaign, $contact);
        $mailer->sendStep($automation->getOrderedSteps()[0], $contact, 'Welcome', 'Hello');
        self::assertEmailCount(2);

        foreach ([0, 1] as $index) {
            $email = self::getMailerMessage($index);
            self::assertInstanceOf(Email::class, $email);
            self::assertEmailHtmlBodyContains($email, 'Brand heading');
            self::assertEmailHtmlBodyContains($email, 'Brand signature');
            self::assertEmailHtmlBodyContains($email, 'background-color:#f3f4f6');
            self::assertEmailTextBodyContains($email, 'Brand heading');
            self::assertEmailTextBodyContains($email, 'Brand signature');
        }
    }

    public function testCampaignsTestsAndAutomationStepsUseConfiguredTwigTemplates(): void
    {
        $audience = $this->createAudience();
        $audience->emailLayout = 'twig:pushword_letter';
        $audience->headerMarkdown = 'Unused audience header';

        $contact = $this->createContact($audience, 'reader@example.tld');
        $campaign = $this->createCampaign($audience);
        $campaign->emailLayout = 'twig:pushword';

        $mailer = self::getContainer()->get(NewsletterMailer::class);
        $mailer->sendCampaign($campaign, $contact);
        $mailer->sendTest($audience, 'Test', 'Hello', null, 'test@example.tld', null, emailLayout: 'twig:pushword');
        self::assertSame('twig:pushword_letter', $audience->emailLayout);
        $audience->emailLayout = 'twig:pushword';
        $automation = $this->createAutomation($audience, [['delay' => 0, 'subject' => 'Welcome']]);
        $mailer->sendStep($automation->getOrderedSteps()[0], $contact, 'Welcome', 'Hello', SendContext::fromAudience($audience));
        self::assertEmailCount(3);
        foreach ([0, 1, 2] as $index) {
            $email = self::getMailerMessage($index);
            self::assertInstanceOf(Email::class, $email);
            self::assertEmailHtmlBodyContains($email, 'font-size:24px');
            self::assertEmailHtmlBodyContains($email, 'Pushword');
            self::assertEmailHtmlBodyNotContains($email, 'Unused audience header');
            self::assertEmailTextBodyNotContains($email, 'Unused audience header');
        }
    }

    public function testDripsBypassTheAsyncBusAndKeepTransportListeners(): void
    {
        $container = self::getContainer();
        $transport = $container->get(TransportInterface::class);
        $bus = $this->createMock(MessageBusInterface::class);
        // Both tracked sends bypass Messenger to measure actual transport acceptance.
        $bus->expects(self::never())->method('dispatch')
            ->with(self::isInstanceOf(SendEmailMessage::class), self::anything())
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));
        $dispatcher = $container->get(EventDispatcherInterface::class);
        $dispatcher->addSubscriber(new EnvelopeListener('bounces@example.tld'));

        $mailer = new NewsletterMailer(
            new Mailer($transport, $bus, $dispatcher),
            $container->get(MailRenderer::class),
            $container->get(LinkGenerator::class),
            $container->get(SiteRegistry::class),
            $container->get(TranslatorInterface::class),
            $container->get(BounceSignature::class),
            $transport,
            $this->entityManager,
            $container->get(SenderProfileRegistry::class),
        );
        $audience = $this->createAudience();
        $contact = $this->createContact($audience, 'reader@example.tld');
        $automation = $this->createAutomation($audience, [['delay' => 0, 'subject' => 'Drip']]);
        $mailer->sendStep($automation->getOrderedSteps()[0], $contact, 'Drip', 'Body');
        self::assertEmailCount(1);
        self::assertQueuedEmailCount(0);
        $event = self::getMailerEvent();
        self::assertInstanceOf(MessageEvent::class, $event);
        self::assertEmailIsNotQueued($event);
        self::assertSame('bounces@example.tld', $event->getEnvelope()->getSender()->getAddress());

        $campaign = new Campaign();
        $campaign->audience = $audience;
        $campaign->subject = 'Ordinary campaign';
        $campaign->bodyMarkdown = 'Body';

        $this->entityManager->persist($campaign);
        $mailer->sendCampaign($campaign, $contact);
        self::assertQueuedEmailCount(0);
        self::assertEmailCount(2);
    }
}
