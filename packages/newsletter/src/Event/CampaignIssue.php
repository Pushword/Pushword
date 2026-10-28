<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Event;

final readonly class CampaignIssue
{
    /** @param 'error'|'warning' $severity */
    public function __construct(
        public string $code,
        public string $message,
        public string $field,
        public string $severity = 'error',
        public ?string $locale = null,
        public ?string $block = null,
    ) {
    }

    /** @return array{code: string, message: string, field: string, severity: string, locale: ?string, block: ?string} */
    public function toArray(): array
    {
        return ['code' => $this->code, 'message' => $this->message, 'field' => $this->field, 'severity' => $this->severity, 'locale' => $this->locale, 'block' => $this->block];
    }
}
