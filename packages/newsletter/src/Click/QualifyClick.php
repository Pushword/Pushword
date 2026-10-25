<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Click;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

/** Listeners may flag a scanner; unclassified clicks remain raw measurements. */
final class QualifyClick extends Event
{
    public ?bool $automated = null;

    public string $method = 'unclassified';

    public function __construct(public readonly Request $request, public readonly ClickPayload $payload)
    {
    }
}
