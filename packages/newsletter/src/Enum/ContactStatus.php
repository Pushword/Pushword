<?php

namespace Pushword\Newsletter\Enum;

enum ContactStatus: string
{
    /** Waiting for the double opt-in confirmation. Never mailable but for the confirmation itself. */
    case Pending = 'pending';

    case Subscribed = 'subscribed';

    case Unsubscribed = 'unsubscribed';

    /** A permanent delivery failure. Terminal: only a new explicit opt-in revives it. */
    case Bounced = 'bounced';
}
