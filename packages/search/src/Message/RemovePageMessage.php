<?php

namespace Pushword\Search\Message;

final readonly class RemovePageMessage
{
    public function __construct(
        public int $pageId,
        public string $host,
    ) {
    }
}
