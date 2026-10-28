<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Validation;

use LogicException;
use Pushword\Newsletter\Event\CampaignIssue;

final class CampaignValidationFailed extends LogicException
{
    /** @param list<CampaignIssue> $issues */
    public function __construct(public readonly array $issues)
    {
        parent::__construct('Campaign validation failed: '.implode(', ', array_column($issues, 'code')));
    }
}
