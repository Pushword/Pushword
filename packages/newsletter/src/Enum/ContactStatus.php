<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Enum;

enum ContactStatus: string
{
    /** Known to the audience, without an opt-in or a confirmation request. */
    case Prospect = 'prospect';

    /** Waiting for the double opt-in confirmation. Never mailable but for the confirmation itself. */
    case Pending = 'pending';

    case Subscribed = 'subscribed';

    case Unsubscribed = 'unsubscribed';

    /** A permanent delivery failure. Terminal: only a new explicit opt-in revives it. */
    case Bounced = 'bounced';
}
