<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Tests\Service;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Newsletter\Block\BlockContent;
use Pushword\Newsletter\Block\BlockMarkup;
use Pushword\Newsletter\Service\MailRenderer;
use Pushword\Newsletter\Service\NewsletterMailer;
use Pushword\Newsletter\Service\SenderProfileRegistry;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\Mime\Email;

#[Group('integration')]
final class SenderProfileRegistryTest extends AbstractNewsletterTestCase
{
    use MailerAssertionsTrait;

    /** @return iterable<string, array{string}> */
    public static function layouts(): iterable
    {
        foreach (['simple', 'framed', 'personal'] as $layout) {
            yield $layout => [$layout];
        }
    }

    #[DataProvider('layouts')]
    public function testPreviewTestAndDeliveryShareTheSelectedIdentityAndKeepConsent(string $layout): void
    {
        $profiles = new SenderProfileRegistry(['editor' => ['label' => 'Editor', 'from_email' => 'editor@example.test', 'from_name' => 'The editor', 'reply_to' => 'reply@example.test', 'signature_markdown' => '**Editorial team**']]);
        self::getContainer()->set(SenderProfileRegistry::class, $profiles);
        $audience = $this->createAudience();
        $campaign = $this->createCampaign($audience);
        $campaign->emailLayout = $layout;
        $campaign->senderProfile = 'editor';
        $campaign->bodyMarkdown = BlockMarkup::encode('library', new BlockContent('42', 'Reusable introduction', 'My edited **introduction**.'));

        $contact = $this->createContact($audience, 'reader@example.test');
        $context = $profiles->resolve($campaign);
        self::assertNotNull($context);
        self::assertSame(['Editor' => 'editor'], $profiles->choices());
        self::assertSame('editor', $campaign->duplicate()->senderProfile);
        self::assertSame('newsletter@localhost.dev', $audience->fromEmail);
        $html = self::getContainer()->get(MailRenderer::class)->html($audience, $contact, $campaign->subject, $campaign->bodyMarkdown, null, 'https://example.test/unsubscribe', null, sendContext: $context, emailLayout: $layout);
        self::assertStringContainsString('<strong>Editorial team</strong>', $html);
        self::assertStringContainsString('https://example.test/unsubscribe', $html);
        $mailer = self::getContainer()->get(NewsletterMailer::class);
        $mailer->sendCampaign($campaign, $contact);
        $mailer->sendTest($audience, $campaign->subject, $campaign->bodyMarkdown, null, 'test@example.test', null, emailLayout: $layout, sendContext: $context);
        self::assertEmailCount(2);
        foreach ([0, 1] as $index) {
            $email = self::getMailerMessage($index);
            self::assertInstanceOf(Email::class, $email);
            self::assertSame('editor@example.test', $email->getFrom()[0]->getAddress());
            self::assertSame('The editor', $email->getFrom()[0]->getName());
            self::assertSame('reply@example.test', $email->getReplyTo()[0]->getAddress());
            self::assertEmailHtmlBodyContains($email, '<strong>Editorial team</strong>');
            self::assertEmailHtmlBodyContains($email, 'My edited <strong>introduction</strong>.');
            self::assertEmailTextBodyContains($email, 'My edited **introduction**.');
            self::assertEmailTextBodyNotContains($email, 'newsletter-block');
            self::assertEmailTextBodyContains($email, '**Editorial team**');
            self::assertEmailHtmlBodyContains($email, 'Unsubscribe');
        }

        $sent = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $sent);
        self::assertTrue($sent->getHeaders()->has('List-Unsubscribe'));
        $campaign->senderProfile = null;
        self::assertNull($profiles->resolve($campaign));
    }

    public function testMissingReplyToAndSignatureKeepTheAudienceSettings(): void
    {
        $audience = $this->createAudience();
        $audience->replyTo = 'audience@example.test';
        $audience->signatureMarkdown = '**Audience signature**';

        $campaign = $this->createCampaign($audience);
        $campaign->senderProfile = 'editor';

        $profiles = new SenderProfileRegistry(['editor' => ['label' => 'Editor', 'from_email' => 'editor@example.test', 'from_name' => 'Editor', 'reply_to' => null, 'signature_markdown' => null]]);
        $context = $profiles->resolve($campaign);
        self::assertNotNull($context);
        self::assertSame('audience@example.test', $context->replyTo);
        $contact = $this->createContact($audience, 'reader@example.test');
        self::assertStringContainsString('Audience signature', self::getContainer()->get(MailRenderer::class)->text($audience, $contact, 'Body', null, $context, 'personal'));
    }

    public function testUnknownIdentityCannotBecomeAnArbitraryFromAddress(): void
    {
        $campaign = $this->createCampaign($this->createAudience());
        $campaign->senderProfile = 'unapproved@example.test';
        $this->expectException(InvalidArgumentException::class);
        new SenderProfileRegistry()->resolve($campaign);
    }
}
