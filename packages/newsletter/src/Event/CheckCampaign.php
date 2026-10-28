<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Event;

use Pushword\Newsletter\Entity\Campaign;

/** Local, synchronous checks. Listeners append diagnostics; they never send mail. */
final class CheckCampaign
{
    /** @var list<CampaignIssue> */
    public array $issues = [];

    /**
     * @param array<string, array{subject: string, preheader: ?string, bodyMarkdown: string}> $contents
     * @param array<string, string>                                                           $html     rendered without tracking or a real recipient
     */
    public function __construct(
        public readonly Campaign $campaign,
        public readonly array $contents,
        public readonly array $html,
    ) {
    }
}
