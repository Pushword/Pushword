<?php

namespace Pushword\StaticGenerator\Cache\Message;

final readonly class HostCacheRefreshMessage
{
    public function __construct(
        public string $host,
    ) {
    }
}
