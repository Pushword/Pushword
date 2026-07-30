<?php

namespace Pushword\Newsletter\Utm;

use Cocur\Slugify\Slugify;
use Pushword\Newsletter\Entity\Automation;
use Pushword\Newsletter\Entity\AutomationStep;
use Pushword\Newsletter\Entity\Campaign;

/** What one mail is called in the destination site's analytics. */
final readonly class UtmTag
{
    public function __construct(
        public string $campaign,
        public ?string $content = null,
    ) {
    }

    public static function forCampaign(Campaign $campaign): self
    {
        return new self($campaign->getSlug());
    }

    /**
     * A drip counts as one campaign, each step as a content variant of it — so
     * the sequence can be read as a whole and step by step.
     *
     * Unlike a campaign, an automation has no frozen slug: renaming one renames
     * it in analytics too.
     */
    public static function forStep(Automation $automation, AutomationStep $step): self
    {
        return new self(
            new Slugify()->slugify($automation->getName()),
            'step-'.($step->getPosition() + 1),
        );
    }
}
