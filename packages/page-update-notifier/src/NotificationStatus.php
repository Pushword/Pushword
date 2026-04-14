<?php

namespace Pushword\PageUpdateNotifier;

enum NotificationStatus: int
{
    case ErrorNoEmail = 1;
    case ErrorNoInterval = 2;
    case WasEverRunSinceInterval = 3;
    case NothingToNotify = 4;
}
