<?php

declare(strict_types=1);

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
        public ?string $id = null,
    ) {
    }

    public static function forCampaign(Campaign $campaign): self
    {
        return new self($campaign->slug, id: $campaign->automation?->getUtmId() ?? $campaign->getUtmId());
    }

    /**
     * A drip counts as one campaign, each step as a content variant of it — so
     * the sequence can be read as a whole and step by step.
     *
     * Editorial names may change; utm_id never does.
     */
    public static function forStep(Automation $automation, AutomationStep $step): self
    {
        return new self(
            new Slugify()->slugify($automation->name),
            'step-'.($step->position + 1),
            $automation->getUtmId(),
        );
    }
}
