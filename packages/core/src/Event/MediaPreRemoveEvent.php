<?php

declare(strict_types=1);

namespace Pushword\Core\Event;

use Pushword\Core\Entity\Media;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Asks whether a media may be removed, before any interactive path removes it: the
 * admin delete and batch delete, the multi-upload delete, `DELETE /api/media` and
 * `pw:media:clean-unused`.
 *
 * A listener refuses with a reason the editor can read; every listener is asked, so
 * all the reasons come back together. The dry run of `pw:media:clean-unused` asks
 * too, so a listener must decide without side effects.
 *
 * @api
 */
final class MediaPreRemoveEvent extends Event
{
    public const string NAME = PushwordEvents::MEDIA_PRE_REMOVE;

    /** @var list<string> */
    private array $reasons = [];

    public function __construct(
        public readonly Media $media,
    ) {
    }

    public function refuse(string $reason): void
    {
        $this->reasons[] = $reason;
    }

    /** @return list<string> */
    public function getReasons(): array
    {
        return $this->reasons;
    }

    public function isRefused(): bool
    {
        return [] !== $this->reasons;
    }
}
