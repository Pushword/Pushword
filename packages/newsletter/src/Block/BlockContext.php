<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Block;

use DateTimeImmutable;

final readonly class BlockContext
{
    public function __construct(public string $mainHost, public string $locale, public ?DateTimeImmutable $scheduledAt)
    {
    }
}
