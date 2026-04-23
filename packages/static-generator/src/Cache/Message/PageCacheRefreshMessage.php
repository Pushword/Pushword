<?php

namespace Pushword\StaticGenerator\Cache\Message;

final readonly class PageCacheRefreshMessage
{
    public function __construct(
        public int $pageId,
    ) {
    }
}
