<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Tests\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Newsletter\Delivery\SendContext;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Entity\EmailTemplate;
use Pushword\Newsletter\Service\BounceSignature;
use Pushword\Newsletter\Service\LinkGenerator;
use Pushword\Newsletter\Service\MailRenderer;
use Pushword\Newsletter\Service\NewsletterMailer;
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

    public function testCampaignsTestsAndAutomationStepsUseASavedTemplateInBothParts(): void
    {
        $audience = $this->createAudience();
        $audience->emailLayout = 'twig:pushword';

        $contact = $this->createContact($audience, 'reader@example.tld');
        $template = new EmailTemplate();
        $template->name = 'mail-template-'.bin2hex(random_bytes(6));
        $template->headerMarkdown = 'Saved brand heading';
        $template->footerMarkdown = 'Saved brand signature';

        $this->entityManager->persist($template);
        $this->entityManager->flush();

        try {
            $layout = 'saved:'.$template->id;
            $campaign = $this->createCampaign($audience);
            $campaign->emailLayout = $layout;
            $mailer = self::getContainer()->get(NewsletterMailer::class);
            $mailer->sendCampaign($campaign, $contact);
            $mailer->sendTest($audience, 'Test', 'Hello', null, 'test@example.tld', null, emailLayout: $layout);
            self::assertSame('twig:pushword', $audience->emailLayout);
            $audience->emailLayout = $layout;
            $automation = $this->createAutomation($audience, [['delay' => 0, 'subject' => 'Welcome']]);
            $mailer->sendStep($automation->getOrderedSteps()[0], $contact, 'Welcome', 'Hello', SendContext::fromAudience($audience));
            self::assertEmailCount(3);
            foreach ([0, 1, 2] as $index) {
                $email = self::getMailerMessage($index);
                self::assertInstanceOf(Email::class, $email);
                self::assertEmailHtmlBodyContains($email, 'Saved brand heading');
                self::assertEmailHtmlBodyContains($email, 'Saved brand signature');
                self::assertEmailTextBodyContains($email, 'Saved brand heading');
                self::assertEmailTextBodyContains($email, 'Saved brand signature');
            }
        } finally {
            $this->entityManager->remove($template);
            $this->entityManager->flush();
        }
    }

    public function testDripsBypassTheAsyncBusAndKeepTransportListeners(): void
    {
        $container = self::getContainer();
        $transport = $container->get(TransportInterface::class);
        $bus = $this->createMock(MessageBusInterface::class);
        // Only the campaign reaches this bus, even though the ordinary mailer
        // is configured with Messenger for both calls.
        $bus->expects(self::once())->method('dispatch')
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

        $mailer->sendCampaign($campaign, $contact);
        self::assertQueuedEmailCount(1);
        self::assertEmailCount(1);
    }
}
