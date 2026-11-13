<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Enum;

enum SmsState: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
