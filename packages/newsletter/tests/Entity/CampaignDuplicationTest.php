<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Tests\Entity;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Automation;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Enum\EditorialStage;

final class CampaignDuplicationTest extends TestCase
{
    public function testDuplicateCopiesAuthoredSettingsAndResetsDeliveryAndAttribution(): void
    {
        $original = new Campaign();
        $original->audience = new Audience();
        $original->subject = 'Autumn news';
        $original->workingTitle = 'Autumn editorial plan';
        $original->internalNotes = 'Review internally';
        $original->editorialStage = EditorialStage::Ready;
        $original->preheader = 'A short introduction';
        $original->bodyMarkdown = 'Hello **%firstName|there%**. [Read more](/news)';
        $original->translations = ['fr' => ['subject' => 'Les nouvelles', 'bodyMarkdown' => 'Bonjour']];
        $original->segment = ['and' => [['tag' => 'hiking']]];
        $original->emailLayout = 'saved:12';
        $original->transactional = true;
        $original->rateSeconds = 12;
        $original->slug = 'original-attribution';
        $original->triggeredBy(new Automation(), 42);
        $original->automationStepId = 'original-step';
        $original->schedule(new DateTimeImmutable('-1 day'));
        $original->markSending(5)->incrementSent()->incrementFailed()->incrementUnsub()->incrementBounce()->incrementClick()->markSent();
        $identity = $original->getUtmId();

        $copy = $original->duplicate();

        foreach (['audience', 'subject', 'workingTitle', 'internalNotes', 'preheader', 'bodyMarkdown', 'translations', 'segment', 'emailLayout', 'transactional', 'rateSeconds'] as $field) {
            self::assertSame($original->$field, $copy->$field, $field);
        }

        self::assertTrue($copy->isDraft());
        self::assertSame(EditorialStage::Draft, $copy->editorialStage);
        self::assertSame(1, $copy->version);
        foreach (['id', 'scheduledAt', 'sentAt', 'automation', 'triggerSubjectId', 'automationStepId'] as $field) {
            self::assertNull($copy->$field, $field);
        }

        foreach (['recipientCount', 'sentCount', 'failedCount', 'unsubCount', 'bounceCount', 'clickCount'] as $field) {
            self::assertSame(0, $copy->$field, $field);
        }

        self::assertSame('autumn-news', $copy->slug);
        self::assertNotSame($identity, $copy->getUtmId());
        self::assertNotSame($original->createdAt, $copy->createdAt);
        self::assertSame($identity, $original->getUtmId());
        self::assertSame('sent', $original->getStatusLabel());
        $copy->mergeTranslations(['fr' => ['subject' => 'New subject']]);
        self::assertSame('Les nouvelles', $original->contentFor('fr')['subject']);
    }

    public function testAnIncompleteDraftCanBeCopiedWithoutInventingSettings(): void
    {
        $copy = new Campaign()->duplicate();

        self::assertTrue($copy->isDraft());
        self::assertNull($copy->audience);
        self::assertNull($copy->emailLayout);
        self::assertNull($copy->rateSeconds);
        self::assertFalse($copy->transactional);
        self::assertSame('', $copy->bodyMarkdown);
        self::assertSame([], $copy->translations);
        self::assertSame([], $copy->segment);
    }
}
