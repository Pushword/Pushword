<?php

namespace Pushword\Newsletter\Enum;

enum RecipientState: string
{
    case Pending = 'pending';

    case Sent = 'sent';

    /** The contact left the audience between arming and sending. Not a failure. */
    case Skipped = 'skipped';

    /** The transport refused the mail. The reason is kept on the row. */
    case Failed = 'failed';

    case Bounced = 'bounced';
}
