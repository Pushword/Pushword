<?php

namespace Pushword\Newsletter\Tests\Entity;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Service\CampaignSender;
use Pushword\Newsletter\Tests\AbstractNewsletterTestCase;

#[Group('integration')]
final class CampaignSlugTest extends AbstractNewsletterTestCase
{
    public function testItIsDerivedFromTheSubject(): void
    {
        $campaign = $this->createCampaign($this->createAudience(), subject: 'Nos nouveautés de janvier');

        self::assertSame('nos-nouveautes-de-janvier', $campaign->getSlug());
    }

    /** Renaming a campaign halfway through its reporting is worse than an ugly name. */
    public function testItDoesNotFollowALaterSubjectEdit(): void
    {
        $campaign = $this->createCampaign($this->createAudience(), subject: 'Janvier');
        $campaign->setSubject('Février');

        $this->entityManager->flush();

        self::assertSame('janvier', $campaign->getSlug());
    }

    public function testArmingStampsTheSendDate(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'reader@example.tld');
        $campaign = $this->createCampaign($audience, subject: 'Janvier');

        self::getContainer()->get(CampaignSender::class)->arm($campaign);

        self::assertSame(date('ymd').'-janvier', $campaign->getSlug());
    }

    /** Re-arming re-dates the campaign; it does not stack another prefix. */
    public function testTheDatePrefixIsNotStacked(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'reader@example.tld');
        $campaign = $this->createCampaign($audience, subject: 'Janvier');

        $sender = self::getContainer()->get(CampaignSender::class);
        $sender->arm($campaign);

        $campaign->revertToDraft();
        $sender->arm($campaign);

        self::assertSame(date('ymd').'-janvier', $campaign->getSlug());
    }

    /**
     * A subject may run to 255 characters and the column holds 128, prefix
     * included. SQLite would swallow the overflow; MariaDB refuses the write.
     */
    public function testALongSubjectStillFitsTheColumnOnceDated(): void
    {
        $audience = $this->createAudience();
        $this->createContact($audience, 'reader@example.tld');
        $campaign = $this->createCampaign($audience, subject: str_repeat('nouveauté ', 25));

        self::assertLessThanOrEqual(120, \strlen($campaign->getSlug()));

        self::getContainer()->get(CampaignSender::class)->arm($campaign);

        self::assertLessThanOrEqual(128, \strlen($campaign->getSlug()));
        self::assertStringStartsWith(date('ymd').'-nouveaute', $campaign->getSlug());
    }

    public function testAnExplicitSlugIsNormalised(): void
    {
        $campaign = new Campaign()
            ->setAudience($this->createAudience())
            ->setSubject('Hello')
            ->setSlug('Été 2026 Promo');

        $this->entityManager->persist($campaign);
        $this->entityManager->flush();

        self::assertSame('ete-2026-promo', $campaign->getSlug());
    }
}
