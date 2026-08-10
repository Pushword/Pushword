<?php

namespace Pushword\Newsletter\Click;

/**
 * What a click link carries, once its signature has been checked: who clicked,
 * which mail carried the link, and where the link was pointing.
 */
final readonly class ClickPayload
{
    public function __construct(
        public int $contactId,
        public string $url,
        public ?int $campaignId = null,
        public ?int $automationId = null,
        public ?int $position = null,
    ) {
    }
}
