<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Tests\Service;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Newsletter\Entity\Campaign;
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
use Symfony\Contracts\Translation\TranslatorInterface;

#[Group('integration')]
final class NewsletterMailerTest extends AbstractNewsletterTestCase
{
    use MailerAssertionsTrait;

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
