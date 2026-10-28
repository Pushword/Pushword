<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Tests\Validation;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Newsletter\Block\BlockContent;
use Pushword\Newsletter\Block\BlockContext;
use Pushword\Newsletter\Block\BlockMarkup;
use Pushword\Newsletter\Block\BlockProviderInterface;
use Pushword\Newsletter\Block\BlockRegistry;
use Pushword\Newsletter\Event\CampaignIssue;
use Pushword\Newsletter\Event\CheckCampaign;
use Pushword\Newsletter\Repository\EmailTemplateRepository;
use Pushword\Newsletter\Service\CampaignSender;
use Pushword\Newsletter\Service\EmailTemplateRegistry;
use Pushword\Newsletter\Service\MailRenderer;
use Pushword\Newsletter\Service\SenderProfileRegistry;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;
use Pushword\Newsletter\Validation\CampaignPreflight;
use Pushword\Newsletter\Validation\CampaignValidationFailed;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;

#[Group('integration')]
final class CampaignPreflightTest extends AbstractNewsletterTestCase
{
    public function testInvalidLinksInAnyTranslationBlockSendingAndWarningsDoNot(): void
    {
        $campaign = $this->createCampaign($this->createAudience());
        $campaign->subject = str_repeat('Long subject ', 8);
        $campaign->translations = ['fr' => ['bodyMarkdown' => '[Reserve]()']];

        $preflight = self::getContainer()->get(CampaignPreflight::class);
        $issues = $preflight->check($campaign);
        self::assertContains('subject.long', array_column($issues, 'code'));
        $links = array_values(array_filter($issues, static fn (CampaignIssue $issue): bool => 'link.invalid' === $issue->code));
        self::assertCount(1, $links);
        self::assertSame('fr', $links[0]->locale);

        try {
            self::getContainer()->get(CampaignSender::class)->arm($campaign);
            self::fail('An invalid translated CTA must prevent recipients from being materialized.');
        } catch (CampaignValidationFailed) {
            self::assertTrue($campaign->isDraft());
            self::assertSame(0, $campaign->recipientCount);
            self::assertSame('link.invalid', $campaign->sendIssues[0]['code']);
        }

        $campaign->translations = ['fr' => ['bodyMarkdown' => '[Reserve](https://example.test/book)']];
        self::getContainer()->get(CampaignSender::class)->schedule($campaign, new DateTimeImmutable('+1 day'));
        self::assertTrue($campaign->isScheduled());
        self::assertSame([], $campaign->sendIssues);
    }

    public function testMarketingTemplatesMustActuallyRenderUnsubscribe(): void
    {
        $twig = self::getContainer()->get(Environment::class);
        $loader = $twig->getLoader();
        $twig->setLoader(new ChainLoader([new ArrayLoader(['newsletter-check-fixture' => '<p>{{ body|raw }}</p>']), $loader]));
        self::getContainer()->set(EmailTemplateRegistry::class, new EmailTemplateRegistry(['fixture' => ['label' => 'Fixture', 'template' => 'newsletter-check-fixture']], self::getContainer()->get(EmailTemplateRepository::class)));

        try {
            $campaign = $this->createCampaign($this->createAudience());
            $campaign->emailLayout = 'twig:fixture';
            $preflight = self::getContainer()->get(CampaignPreflight::class);
            self::assertSame('unsubscribe.missing', $preflight->check($campaign)[0]->code);
            $campaign->transactional = true;
            self::assertSame([], $preflight->check($campaign));
        } finally {
            $twig->setLoader($loader);
        }
    }

    public function testTheExtensionReceivesContentAndRenderingAndCanBlock(): void
    {
        $campaign = $this->createCampaign($this->createAudience());
        $events = new EventDispatcher();
        $events->addListener(CheckCampaign::class, static function (CheckCampaign $event) use ($campaign): void {
            self::assertSame($campaign, $event->campaign);
            self::assertSame($campaign->subject, $event->contents['']['subject']);
            self::assertStringContainsString('<html', $event->html['']);
            $event->issues[] = new CampaignIssue('selection.expired', 'Selected content has expired.', 'bodyMarkdown');
        });
        $preflight = new CampaignPreflight(self::getContainer()->get(MailRenderer::class), self::getContainer()->get(SiteRegistry::class), $events, new SenderProfileRegistry(), new BlockRegistry([]));
        self::assertSame('selection.expired', $preflight->check($campaign)[0]->code);
        $this->expectException(CampaignValidationFailed::class);
        $preflight->assertCanSend($campaign);
    }

    public function testAProviderChecksTheSavedReferenceAndManualCopyWithSendContext(): void
    {
        $campaign = $this->createCampaign($this->createAudience());
        $campaign->scheduledAt = new DateTimeImmutable('+1 month');
        $campaign->translations = ['fr' => ['bodyMarkdown' => BlockMarkup::encode('products', new BlockContent('stable-42', 'Selected product', 'My **edited** copy', ['category' => 'winter']))]];

        $provider = $this->createMock(BlockProviderInterface::class);
        $provider->method('type')->willReturn('products');
        $provider->expects(self::once())->method('check')->willReturnCallback(static function (BlockContent $block, BlockContext $context) use ($campaign): array {
            self::assertSame('stable-42', $block->reference);
            self::assertSame('My **edited** copy', $block->markdown);
            self::assertSame(['category' => 'winter'], $block->parameters);
            self::assertSame('localhost.dev', $context->mainHost);
            self::assertSame('fr', $context->locale);
            self::assertSame($campaign->scheduledAt, $context->scheduledAt);

            return [new CampaignIssue('product.expired', 'Product expired.', 'bodyMarkdown')];
        });
        $preflight = new CampaignPreflight(self::getContainer()->get(MailRenderer::class), self::getContainer()->get(SiteRegistry::class), new EventDispatcher(), new SenderProfileRegistry(), new BlockRegistry([$provider]));
        $issues = $preflight->check($campaign);
        self::assertCount(1, $issues);
        self::assertSame('product.expired', $issues[0]->code);
        self::assertSame('Selected product', $issues[0]->block);
        self::assertSame('fr', $issues[0]->locale);
        self::assertStringContainsString('edited', $campaign->contentFor('fr')['bodyMarkdown']);
    }

    public function testUnknownProvidersAndMalformedBlocksFailClosed(): void
    {
        $campaign = $this->createCampaign($this->createAudience());
        $campaign->bodyMarkdown = BlockMarkup::encode('missing', new BlockContent('42', 'Missing item', 'Saved text'));

        $preflight = self::getContainer()->get(CampaignPreflight::class);
        self::assertContains('block.unavailable', array_column($preflight->check($campaign), 'code'));
        $campaign->bodyMarkdown = "```newsletter-block\ninvalid\n```";
        self::assertContains('render.failed', array_column($preflight->check($campaign), 'code'));
        $campaign->bodyMarkdown = BlockMarkup::encode('library', new BlockContent('42', 'Empty copy', ''));
        self::assertContains('bodyMarkdown.missing', array_column($preflight->check($campaign), 'code'));
        $campaign->bodyMarkdown = 'Valid body';
        $campaign->senderProfile = 'removed';
        self::assertContains('sender.invalid', array_column($preflight->check($campaign), 'code'));
    }

    public function testSchedulingValidatesTheRequestedDateBeforeAuthorizing(): void
    {
        $when = new DateTimeImmutable('+1 month');
        $provider = $this->createMock(BlockProviderInterface::class);
        $provider->method('type')->willReturn('products');
        $provider->expects(self::exactly(2))->method('check')->willReturnCallback(static function (BlockContent $block, BlockContext $context) use ($when): array {
            self::assertSame($when, $context->scheduledAt);

            return [new CampaignIssue('product.expired', 'Product expired.', 'bodyMarkdown')];
        });
        self::getContainer()->set(BlockRegistry::class, new BlockRegistry([$provider]));

        foreach ([null, new DateTimeImmutable('+1 day')] as $previousDate) {
            $campaign = $this->createCampaign($this->createAudience());
            $campaign->scheduledAt = $previousDate;
            $campaign->bodyMarkdown = BlockMarkup::encode('products', new BlockContent('42', 'Product', 'Saved copy'));

            try {
                self::getContainer()->get(CampaignSender::class)->schedule($campaign, $when);
                self::fail('The requested date must be checked before authorizing delivery.');
            } catch (CampaignValidationFailed $failure) {
                self::assertSame(['product.expired'], array_column($failure->issues, 'code'));
            }

            self::assertTrue($campaign->isDraft());
            self::assertSame($when, $campaign->scheduledAt);
            self::assertSame(0, $campaign->recipientCount);
        }
    }
}
