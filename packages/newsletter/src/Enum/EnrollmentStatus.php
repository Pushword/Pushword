<?php

namespace Pushword\Newsletter\Enum;

enum EnrollmentStatus: string
{
    case Active = 'active';

    /** Every step was sent. */
    case Done = 'done';

    /** A stop condition matched, or the contact left the audience. */
    case Stopped = 'stopped';
}
