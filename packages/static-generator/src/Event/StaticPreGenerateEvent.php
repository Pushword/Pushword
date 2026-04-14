<?php

namespace Pushword\StaticGenerator\Event;

use Pushword\Core\Site\SiteConfig;
use Symfony\Contracts\EventDispatcher\Event;

final class StaticPreGenerateEvent extends Event
{
    public function __construct(
        public readonly SiteConfig $app,
        public readonly string $staticDir,
        public readonly bool $incremental,
    ) {
    }
}
