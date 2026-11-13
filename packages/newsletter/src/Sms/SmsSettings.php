<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Sms;

use DateTimeZone;

final readonly class SmsSettings
{
    public function __construct(
        public string $url,
        public string $username,
        public string $password,
        public string $deviceId,
        public int $simNumber,
        public bool $enabled,
        public int $dailyLimit,
        public int $intervalSeconds,
        public int $startHour,
        public int $endHour,
        public string $timezone,
    ) {
        new DateTimeZone($timezone);
    }

    public function isConfigured(): bool
    {
        $secure = str_starts_with($this->url, 'https://')
            || 1 === preg_match('#^http://(?:127\.0\.0\.1|localhost)(?::[0-9]+)?/#', $this->url);

        return $secure && false !== filter_var($this->url, \FILTER_VALIDATE_URL) && '' !== $this->username && '' !== $this->password && '' !== $this->deviceId;
    }

    public function canSend(): bool
    {
        return $this->isConfigured() && $this->enabled && $this->dailyLimit > 0;
    }
}
