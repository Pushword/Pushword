<?php

namespace Pushword\Newsletter\Tests\Utm;

use PHPUnit\Framework\TestCase;
use Pushword\Newsletter\Entity\Automation;
use Pushword\Newsletter\Entity\AutomationStep;
use Pushword\Newsletter\Entity\Campaign;
use Pushword\Newsletter\Utm\UtmTag;

final class UtmTagTest extends TestCase
{
    public function testACampaignIsNamedByItsSlug(): void
    {
        $campaign = new Campaign();
        $campaign->subject = 'Nos nouveautés';

        $tag = UtmTag::forCampaign($campaign);

        self::assertSame('nos-nouveautes', $tag->campaign);
        self::assertNull($tag->content, 'a campaign is one mail, it has no variant');
    }

    /** Steps are stored 0-based but read as "step 1, step 2" by whoever opens the report. */
    public function testAStepIsNumberedFromOneUnderItsAutomationName(): void
    {
        $automation = new Automation();
        $automation->name = 'Bienvenue AmTrek';

        $step = new AutomationStep();
        $step->position = 1;

        $tag = UtmTag::forStep($automation, $step);

        self::assertSame('bienvenue-amtrek', $tag->campaign);
        self::assertSame('step-2', $tag->content);
    }
}
