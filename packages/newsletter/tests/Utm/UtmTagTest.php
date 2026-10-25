<?php

declare(strict_types=1);

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
        $automation->name = 'Bienvenue randonneurs';

        $step = new AutomationStep();
        $step->position = 1;

        $tag = UtmTag::forStep($automation, $step);

        self::assertSame('bienvenue-randonneurs', $tag->campaign);
        self::assertSame('step-2', $tag->content);
    }

    public function testPermanentIdentitiesSurviveEditorialChangesAndStepReordering(): void
    {
        $automation = new Automation();
        $automation->name = 'Before';

        $step = new AutomationStep();
        $tag = UtmTag::forStep($automation, $step);
        self::assertNotNull($tag->id);
        self::assertNotNull($tag->step);
        $automation->name = 'After';
        $step->position = 4;
        $renamed = UtmTag::forStep($automation, $step);
        self::assertSame($tag->id, $renamed->id);
        self::assertSame($tag->step, $renamed->step);
        self::assertNotSame($tag->id, UtmTag::forStep(new Automation(), $step)->id);
    }
}
