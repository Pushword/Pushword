<?php

declare(strict_types=1);

namespace Pushword\StaticGenerator\Event;

use Pushword\Core\Site\SiteConfig;
use Symfony\Contracts\EventDispatcher\Event;

final class StaticPostGenerateEvent extends Event
{
    /**
     * @param array<string> $errors
     */
    public function __construct(
        public readonly SiteConfig $app,
        public readonly string $staticDir,
        public readonly bool $incremental,
        public readonly array $errors = [],
    ) {
    }
}
