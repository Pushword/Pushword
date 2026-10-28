<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Event;

use DateTimeImmutable;
use InvalidArgumentException;

/** Read-only metadata; actions only navigate to the owning tool or an existing report. */
final class CampaignIndexItem
{
    public readonly string $key;

    /**
     * @param array<string, string> $actions labels mapped to navigation URLs; the first opens from the subject
     * @param list<string>          $hosts   site identities, independent of the audience display label; empty means unknown
     */
    public function __construct(
        public readonly string $source,
        public readonly string $id,
        public readonly string $subject,
        public readonly ?string $status = null,
        public readonly ?DateTimeImmutable $scheduledAt = null,
        public readonly ?DateTimeImmutable $sentAt = null,
        public array $actions = [],
        public readonly ?string $audience = null,
        public readonly array $hosts = [],
        public readonly ?string $editorialStage = null,
        public readonly ?int $version = null,
    ) {
        if (1 !== preg_match('/^[a-z][a-z0-9_-]*$/D', $source) || '' === $id) {
            throw new InvalidArgumentException('A campaign index item needs a source identifier and a non-empty ID.');
        }

        $this->key = $source.':'.$id;
        self::validateActions($actions);
    }

    /** @param array<string, string> $actions */
    public static function validateActions(array $actions): void
    {
        foreach ($actions as $url) {
            if (str_contains($url, '\\') || 1 === preg_match('/[\x00-\x20\x7f]/', $url)
                || 1 !== preg_match('~^(?:https?://[^/\s]+|/(?!/))~i', $url)) {
                throw new InvalidArgumentException('Campaign actions must use HTTP(S) URLs or absolute paths.');
            }
        }
    }
}
