<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Enum;

enum DeliveryEventKind: string
{
    case RemoteAccepted = 'remoteAccepted';
    case Deferred = 'deferred';
    case Failed = 'failed';
}
