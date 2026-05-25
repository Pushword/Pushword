<?php

namespace Pushword\Search\Message;

final readonly class ReindexPageMessage
{
    public function __construct(
        public int $pageId,
    ) {
    }
}
