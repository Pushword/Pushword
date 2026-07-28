<?php

namespace Pushword\Newsletter\Enum;

enum CampaignStatus: string
{
    case Draft = 'draft';

    /** Armed for a future date; the tick materialises its recipients when due. */
    case Scheduled = 'scheduled';

    /** Recipients materialised, mails going out at the configured cadence. */
    case Sending = 'sending';

    case Sent = 'sent';
}
