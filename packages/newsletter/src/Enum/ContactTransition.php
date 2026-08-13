<?php

namespace Pushword\Newsletter\Enum;

/**
 * What a {@see \Pushword\Newsletter\Entity\ContactEvent} records — the five acts
 * that move a subscription, one per {@see \Pushword\Newsletter\Service\ContactManager}
 * method.
 */
enum ContactTransition: string
{
    /** A subscription opened, or re-opened by somebody who had left. */
    case OptIn = 'optin';

    /** The double opt-in answered. */
    case Confirm = 'confirm';

    case Unsubscribe = 'unsubscribe';

    /** An opt-out taken back from the undo button on the unsubscribe page. */
    case Resubscribe = 'resubscribe';

    /** Not an act of the person's: the mail server refused their address for good. */
    case Bounce = 'bounce';
}
